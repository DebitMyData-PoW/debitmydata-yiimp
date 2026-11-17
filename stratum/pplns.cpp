// PPLNS paid in the coinbase, with optional solo mining. See pplns.h.

#include "stratum.h"

PPLNS_CONFIG g_pplns;

// The shares table has the solo column (sql/2026-11-12-pplns-coinbase.sql).
bool g_shares_solo_column = false;

static YAAMP_DB *g_pplns_db = NULL;
static pthread_mutex_t g_pplns_mutex;		// g_pplns_db and g_pplns_state
static pthread_mutex_t g_pplns_script_mutex;	// g_pplns_scripts
static bool g_pplns_ready = false;		// tables found

struct PPLNS_STATE
{
	PPLNS_STATE(): loaded(false) {}

	bool loaded;
	bool taken_over;		// the stratum keeps the books (floor_id is set)
	bool closed;			// the closing block was found: PPLNS from now on
	long long floor_id;		// shares up to this id are paid
	time_t last_legacy;		// last old-style moment (start, or old-style block found)

	// share window cache
	int cache_height;
	int cache_mode;
	time_t cache_time;
	long long cache_from, cache_to;
	double cache_window;
	std::vector<PPLNS_PAYEE> cache;
};

static std::map<int, PPLNS_STATE> g_pplns_state;
static std::map<std::string, std::string> g_pplns_scripts;	// address -> scriptPubKey ("" = can't be paid)

static const char *mode_name(int mode)
{
	switch(mode) {
		case PPLNS_MODE_LEGACY: return "legacy";
		case PPLNS_MODE_CLOSE: return "close";
		case PPLNS_MODE_PPLNS: return "pplns";
		case PPLNS_MODE_SOLO: return "solo";
	}
	return "?";
}

///////////////////////////////////////////////////////////////////////////////////////////////
// database (own connection; never exits the stratum on an error)

static bool pplns_query(const char *format, ...)
{
	char buffer[64*1024];
	va_list args;
	va_start(args, format);
	int len = vsnprintf(buffer, sizeof(buffer), format, args);
	va_end(args);
	if(len <= 0 || len >= (int) sizeof(buffer)) {
		stratumlog("pplns: query too long\n");
		return false;
	}

	if(!g_pplns_db) g_pplns_db = db_connect();
	for(int i = 0; i < 2; i++)
	{
		if(!mysql_query(&g_pplns_db->mysql, buffer)) return true;
		int err = mysql_errno(&g_pplns_db->mysql);
		stratumlog("pplns: SQL error %d, %s\n", err, mysql_error(&g_pplns_db->mysql));
		if(err != CR_SERVER_GONE_ERROR && err != CR_SERVER_LOST) return false;
		mysql_close(&g_pplns_db->mysql);
		delete g_pplns_db;
		g_pplns_db = db_connect();
	}
	return false;
}

static MYSQL_RES *pplns_select(const char *format, ...)
{
	char buffer[16*1024];
	va_list args;
	va_start(args, format);
	vsnprintf(buffer, sizeof(buffer), format, args);
	va_end(args);

	if(!pplns_query("%s", buffer)) return NULL;
	return mysql_store_result(&g_pplns_db->mysql);
}

static bool pplns_has_column(const char *table, const char *column)
{
	MYSQL_RES *res = pplns_select("SHOW COLUMNS FROM %s LIKE '%s'", table, column);
	if(!res) return false;
	bool found = mysql_num_rows(res) > 0;
	mysql_free_result(res);
	return found;
}

static bool pplns_has_table(const char *table)
{
	MYSQL_RES *res = pplns_select("SHOW TABLES LIKE '%s'", table);
	if(!res) return false;
	bool found = mysql_num_rows(res) > 0;
	mysql_free_result(res);
	return found;
}

///////////////////////////////////////////////////////////////////////////////////////////////

void pplns_init(dictionary *ini)
{
	g_pplns.enable = iniparser_getint(ini, "PPLNS:enable", 0);
	g_pplns.window = iniparser_getdouble(ini, "PPLNS:window", 2.0);
	g_pplns.fee = iniparser_getdouble(ini, "PPLNS:fee", 0.5);
	g_pplns.solo = iniparser_getint(ini, "PPLNS:solo", 1);
	g_pplns.solo_fee = iniparser_getdouble(ini, "PPLNS:solo_fee", g_pplns.fee);
	g_pplns.max_outputs = iniparser_getint(ini, "PPLNS:max_outputs", 50);
	g_pplns.min_output = iniparser_getdouble(ini, "PPLNS:min_output", 0.1);
	g_pplns.refresh = iniparser_getint(ini, "PPLNS:refresh", 60);
	g_pplns.check_template = iniparser_getint(ini, "PPLNS:check_template", 1);
	g_pplns.wait_legacy = iniparser_getint(ini, "PPLNS:wait_legacy", 600);

	if(g_pplns.window < 0.1) g_pplns.window = 0.1;
	if(g_pplns.max_outputs < 0) g_pplns.max_outputs = 0;
	if(g_pplns.max_outputs > 200) g_pplns.max_outputs = 200;
	if(g_pplns.fee < 0 || g_pplns.fee >= 100) g_pplns.fee = 0;
	if(g_pplns.solo_fee < 0 || g_pplns.solo_fee >= 100) g_pplns.solo_fee = 0;
	if(g_pplns.refresh < 10) g_pplns.refresh = 10;

	pthread_mutex_init(&g_pplns_mutex, 0);
	pthread_mutex_init(&g_pplns_script_mutex, 0);
}

// Called once the database settings are known.
static void pplns_check_tables()
{
	static bool checked = false;
	if(checked) return;
	checked = true;

	g_shares_solo_column = pplns_has_column("shares", "solo");
	bool tables = g_shares_solo_column && pplns_has_table("pplns_state") && pplns_has_table("coinbase_splits");

	if(g_pplns.enable && !tables) {
		stratumlog("pplns: the coinbase payout tables are missing (sql/2026-11-12-pplns-coinbase.sql), PPLNS stays off\n");
		g_pplns.enable = false;
	}
	g_pplns_ready = tables;

	if(g_pplns.enable)
		stratumlog("pplns: on, window %gx network difficulty, fee %g%%, solo %s (fee %g%%), %d outputs max, min %g\n",
			g_pplns.window, g_pplns.fee, g_pplns.solo? "allowed": "off", g_pplns.solo_fee,
			g_pplns.max_outputs, g_pplns.min_output);
}

void pplns_startup()
{
	CommonLock(&g_pplns_mutex);
	pplns_check_tables();
	CommonUnlock(&g_pplns_mutex);
}

///////////////////////////////////////////////////////////////////////////////////////////////
// addresses

static bool standard_script(const char *s)
{
	int len = strlen(s);
	for(int i = 0; i < len; i++) if(!isxdigit(s[i])) return false;
	if(len == 50 && !strncmp(s, "76a914", 6) && !strcmp(s+46, "88ac")) return true;	// P2PKH
	if(len == 46 && !strncmp(s, "a914", 4) && !strcmp(s+44, "87")) return true;		// P2SH
	if(len == 44 && !strncmp(s, "0014", 4)) return true;					// P2WPKH
	if(len == 68 && !strncmp(s, "0020", 4)) return true;					// P2WSH
	if(len == 68 && !strncmp(s, "5120", 4)) return true;					// P2TR
	return false;
}

// The address's scriptPubKey, or "" when it can't be paid in a coinbase.
static std::string address_script(YAAMP_COIND *coind, const char *address)
{
	std::string key = address;

	CommonLock(&g_pplns_script_mutex);
	std::map<std::string, std::string>::iterator it = g_pplns_scripts.find(key);
	if(it != g_pplns_scripts.end()) {
		std::string s = it->second;
		CommonUnlock(&g_pplns_script_mutex);
		return s;
	}
	CommonUnlock(&g_pplns_script_mutex);

	for(const char *p = address; *p; p++)
		if(!isalnum(*p)) return "";

	char params[256];
	snprintf(params, sizeof(params), "[\"%s\"]", address);
	json_value *json = rpc_call(&coind->rpc, "validateaddress", params);
	if(!json) return "";	// node busy: try again next time

	std::string script;
	json_value *result = json_get_object(json, "result");
	if(result && result->type == json_object && json_get_bool(result, "isvalid")) {
		const char *pk = json_get_string(result, "scriptPubKey");
		if(pk && standard_script(pk)) script = pk;
	}
	json_value_free(json);

	if(script.empty()) stratumlog("pplns: %s %s can't be paid in the coinbase, it will be paid the normal way\n", coind->symbol, address);

	CommonLock(&g_pplns_script_mutex);
	g_pplns_scripts[key] = script;
	CommonUnlock(&g_pplns_script_mutex);
	return script;
}

///////////////////////////////////////////////////////////////////////////////////////////////
// coinbase outputs

static void put_value(std::string &s, json_int_t value)
{
	char buf[32];
	uint64_t v = (uint64_t) value;
	for(int i = 0; i < 8; i++) {
		sprintf(buf, "%02x", (unsigned int)((v >> (8*i)) & 0xff));
		s += buf;
	}
}

static void put_varint(std::string &s, uint64_t n)
{
	char buf[32];
	if(n < 0xfd) sprintf(buf, "%02x", (unsigned int) n);
	else sprintf(buf, "fd%02x%02x", (unsigned int)(n & 0xff), (unsigned int)((n >> 8) & 0xff));
	s += buf;
}

static void put_script_output(std::string &s, json_int_t value, const std::string &script)
{
	put_value(s, value);
	put_varint(s, script.size()/2);
	s += script;
}

static std::string pool_output(YAAMP_COIND *coind, json_int_t value)
{
	char buf[1024] = { 0 };
	coinbase_pack_pool_output(coind, buf, value);
	return buf;
}

// outputs count, outputs and locktime
static std::string outputs_classic(YAAMP_COIND *coind, PPLNS_TEMPL *pt)
{
	std::string s;
	put_varint(s, pt->commitment.empty()? 1: 2);
	s += pt->commitment;
	s += pool_output(coind, pt->available);
	s += "00000000";
	return s;
}

static std::string outputs_split(YAAMP_COIND *coind, PPLNS_TEMPL *pt, PPLNS_SPLIT *split)
{
	int n = pt->commitment.empty()? 1: 2;
	for(size_t i = 0; i < split->payees.size(); i++)
		if(split->payees[i].in_coinbase) n++;

	std::string s;
	put_varint(s, n);
	s += pt->commitment;
	for(size_t i = 0; i < split->payees.size(); i++)
		if(split->payees[i].in_coinbase) put_script_output(s, split->payees[i].amount, split->payees[i].script);
	s += pool_output(coind, split->pool_output);
	s += "00000000";
	return s;
}

///////////////////////////////////////////////////////////////////////////////////////////////
// amounts

static bool by_amount(const PPLNS_PAYEE &a, const PPLNS_PAYEE &b)
{
	if(a.amount != b.amount) return a.amount > b.amount;
	return a.userid < b.userid;
}

// From the weights, works out each miner's net amount and who is paid in the coinbase.
static void split_amounts(YAAMP_COIND *coind, PPLNS_SPLIT *split, json_int_t available)
{
	split->reward = available;
	split->fallback = false;

	double total = 0;
	for(size_t i = 0; i < split->payees.size(); i++) total += split->payees[i].weight;

	// account settings: no_fees, donation, address
	std::map<int, int> nofees, donation;
	std::map<int, std::string> username;
	if(!split->payees.empty())
	{
		std::string ids;
		char buf[32];
		for(size_t i = 0; i < split->payees.size(); i++) {
			sprintf(buf, "%s%d", i? ",": "", split->payees[i].userid);
			ids += buf;
		}
		MYSQL_RES *res = pplns_select("SELECT id, username, IFNULL(no_fees,0), IFNULL(donation,0) FROM accounts WHERE id IN (%s)", ids.c_str());
		if(res) {
			MYSQL_ROW row;
			while((row = mysql_fetch_row(res)) != NULL) {
				int id = atoi(row[0]);
				username[id] = row[1]? row[1]: "";
				nofees[id] = atoi(row[2]);
				donation[id] = atoi(row[3]);
			}
			mysql_free_result(res);
		}
	}

	json_int_t paid = 0;
	for(size_t i = 0; i < split->payees.size(); i++)
	{
		PPLNS_PAYEE &p = split->payees[i];
		long double gross = total > 0? floorl((long double) available * p.weight / total): 0;
		long double net = gross;
		double fee = split->mode == PPLNS_MODE_SOLO? g_pplns.solo_fee: g_pplns.fee;
		if(!nofees[p.userid]) net = net - net * fee / 100.0;
		if(donation[p.userid] > 0) net = net - net * donation[p.userid] / 100.0;
		p.amount = (json_int_t) floorl(net);
		if(p.amount < 0) p.amount = 0;
		p.in_coinbase = false;
		p.script = "";
		paid += p.amount;
	}

	std::sort(split->payees.begin(), split->payees.end(), by_amount);

	json_int_t min_output = (json_int_t) (g_pplns.min_output * 100000000.0);
	json_int_t in_coinbase = 0;
	int outputs = 0;
	for(size_t i = 0; i < split->payees.size(); i++)
	{
		PPLNS_PAYEE &p = split->payees[i];
		if(outputs >= g_pplns.max_outputs) break;
		if(p.amount <= 0 || p.amount < min_output) continue;
		if(username.find(p.userid) == username.end()) continue;

		p.script = address_script(coind, username[p.userid].c_str());
		if(p.script.empty()) continue;

		p.in_coinbase = true;
		in_coinbase += p.amount;
		outputs++;
	}

	split->pool_output = available - in_coinbase;
	split->pool_keep = available - paid;

	// the pool wallet must own an output, so the website sees the block
	if(split->pool_output < 1) {
		for(size_t i = 0; i < split->payees.size(); i++) {
			if(!split->payees[i].in_coinbase || split->payees[i].amount < 2) continue;
			split->payees[i].amount--;
			split->pool_output++;
			split->pool_keep++;
			break;
		}
	}
}

// Everyone paid the normal way (the node refused the coinbase, or the template was an old-style one).
static void split_all_normal(PPLNS_SPLIT *split)
{
	for(size_t i = 0; i < split->payees.size(); i++) {
		split->payees[i].in_coinbase = false;
		split->payees[i].script = "";
	}
	split->pool_output = split->reward;
	split->fallback = true;
}

///////////////////////////////////////////////////////////////////////////////////////////////
// state and share window

static PPLNS_STATE &pplns_state(int coinid)
{
	PPLNS_STATE &st = g_pplns_state[coinid];
	if(st.loaded) return st;

	st.loaded = true;
	st.taken_over = false;
	st.closed = false;
	st.floor_id = 0;
	st.last_legacy = time(NULL);	// the old stratum may have found a block just before
	st.cache_height = -1;
	st.cache_time = 0;

	MYSQL_RES *res = pplns_select("SELECT floor_id, IFNULL(closed,0) FROM pplns_state WHERE coinid=%d AND floor_id IS NOT NULL", coinid);
	if(res) {
		MYSQL_ROW row = mysql_fetch_row(res);
		if(row && row[0]) {
			st.taken_over = true;
			st.floor_id = atoll(row[0]);
			st.closed = atoi(row[1]) != 0;
		}
		mysql_free_result(res);
	}
	return st;
}

static void pplns_save_state(int coinid, PPLNS_STATE &st)
{
	int now = time(NULL);
	pplns_query("INSERT INTO pplns_state (coinid, floor_id, closed, switch_time, updated) VALUES (%d, %lld, %d, %d, %d) "
		"ON DUPLICATE KEY UPDATE floor_id=VALUES(floor_id), closed=VALUES(closed), "
		"switch_time=IFNULL(switch_time, VALUES(switch_time)), updated=VALUES(updated)",
		coinid, st.floor_id, st.closed? 1: 0, now, now);
}

// Takes over from the website's PROP once every old-style block has been paid.
static bool pplns_take_over(YAAMP_COIND *coind, PPLNS_STATE &st)
{
	if(st.taken_over) return true;
	// blocks the previous stratum found may still be on their way to the database
	if(time(NULL) - st.last_legacy < g_pplns.wait_legacy) return false;

	long long prop_paid = -1;
	MYSQL_RES *res = pplns_select("SELECT prop_paid_id FROM pplns_state WHERE coinid=%d AND prop_paid_id IS NOT NULL", coind->id);
	if(res) {
		MYSQL_ROW row = mysql_fetch_row(res);
		if(row && row[0]) prop_paid = atoll(row[0]);
		mysql_free_result(res);
	}
	if(prop_paid < 0) return false;		// the updated website hasn't paid a block yet

	int pending = 1;
	res = pplns_select("SELECT COUNT(*) FROM blocks WHERE coin_id=%d AND category='new'", coind->id);
	if(res) {
		MYSQL_ROW row = mysql_fetch_row(res);
		if(row && row[0]) pending = atoi(row[0]);
		mysql_free_result(res);
	}
	if(pending) return false;

	// old-style blocks this stratum found, not paid by the website yet
	pending = 1;
	res = pplns_select("SELECT COUNT(*) FROM coinbase_splits c "
		"LEFT JOIN blocks b ON b.coin_id=c.coinid AND b.blockhash=c.blockhash "
		"WHERE c.coinid=%d AND c.mode=%d AND c.time>UNIX_TIMESTAMP()-86400 AND "
		"((b.id IS NULL AND c.time>UNIX_TIMESTAMP()-120) OR b.category='new')", coind->id, PPLNS_MODE_LEGACY);
	if(res) {
		MYSQL_ROW row = mysql_fetch_row(res);
		if(row && row[0]) pending = atoi(row[0]);
		mysql_free_result(res);
	}
	if(pending) return false;

	// the website's last PROP payment may have moved meanwhile
	res = pplns_select("SELECT prop_paid_id FROM pplns_state WHERE coinid=%d AND prop_paid_id IS NOT NULL", coind->id);
	if(res) {
		MYSQL_ROW row = mysql_fetch_row(res);
		if(row && row[0]) prop_paid = atoll(row[0]);
		mysql_free_result(res);
	}

	st.taken_over = true;
	st.closed = false;
	st.floor_id = prop_paid;
	st.cache_height = -1;
	pplns_save_state(coind->id, st);

	stratumlog("pplns: %s switches to coinbase payouts; shares after id %lld close the PROP round with the next block\n",
		coind->symbol, prop_paid);
	return true;
}

// All the shares not paid yet (the PROP round to close).
static bool read_close(YAAMP_COIND *coind, PPLNS_STATE &st, PPLNS_SPLIT *split)
{
	MYSQL_RES *res = pplns_select("SELECT userid, SUM(difficulty), MIN(id), MAX(id) FROM shares "
		"WHERE coinid=%d AND valid=1 AND solo=0 AND id>%lld GROUP BY userid", coind->id, st.floor_id);
	if(!res) return false;

	split->share_from = 0;
	split->share_to = st.floor_id;
	split->window = 0;
	MYSQL_ROW row;
	while((row = mysql_fetch_row(res)) != NULL)
	{
		if(!row[0] || !row[1]) continue;
		PPLNS_PAYEE p;
		p.userid = atoi(row[0]);
		p.weight = atof(row[1]);
		if(p.userid <= 0 || p.weight <= 0) continue;
		long long from = atoll(row[2]), to = atoll(row[3]);
		if(!split->share_from || from < split->share_from) split->share_from = from;
		if(to > split->share_to) split->share_to = to;
		split->window += p.weight;
		split->payees.push_back(p);
	}
	mysql_free_result(res);
	return true;
}

// The last N shares (N = window x network difficulty), newest first: the shares of the last
// seconds still in memory (written every 20 seconds or so), then the database.
static bool read_window(YAAMP_COIND *coind, PPLNS_STATE &st, PPLNS_SPLIT *split, double window)
{
	std::map<int, double> weights;
	long long before = 0x7fffffffffffffffLL;
	double counted = 0;

	split->share_from = 0;
	split->share_to = 0;

	// held until the database is read too, so a share being written isn't counted twice
	g_list_worker.Enter();
	for(CLI li = g_list_worker.first; li && counted < window; li = li->next)
	{
		YAAMP_WORKER *worker = (YAAMP_WORKER *)li->data;
		if(worker->deleted || !worker->valid || worker->solo) continue;
		if(worker->coinid != coind->id || worker->userid <= 0 || worker->difficulty <= 0) continue;
		double diff = worker->difficulty;
		if(counted + diff > window) diff = window - counted;
		weights[worker->userid] += diff;
		counted += diff;
	}

	while(counted < window)
	{
		MYSQL_RES *res = pplns_select("SELECT id, userid, difficulty FROM shares "
			"WHERE coinid=%d AND valid=1 AND solo=0 AND pid>0 AND id>%lld AND id<%lld ORDER BY id DESC LIMIT 20000",
			coind->id, st.floor_id, before);
		if(!res) {
			g_list_worker.Leave();
			return false;
		}

		int rows = 0;
		MYSQL_ROW row;
		while((row = mysql_fetch_row(res)) != NULL)
		{
			rows++;
			long long id = atoll(row[0]);
			int userid = row[1]? atoi(row[1]): 0;
			double diff = row[2]? atof(row[2]): 0;
			before = id;
			if(userid <= 0 || diff <= 0) continue;

			if(counted + diff > window) diff = window - counted;	// the oldest share counts in part
			weights[userid] += diff;
			counted += diff;
			if(!split->share_to) split->share_to = id;
			split->share_from = id;
			if(counted >= window) break;
		}
		mysql_free_result(res);
		if(rows < 20000) break;
	}
	g_list_worker.Leave();

	split->window = counted;
	for(std::map<int, double>::iterator it = weights.begin(); it != weights.end(); ++it) {
		PPLNS_PAYEE p;
		p.userid = it->first;
		p.weight = it->second;
		split->payees.push_back(p);
	}
	return true;
}

static void pplns_close_empty(YAAMP_COIND *coind, PPLNS_STATE &st)
{
	st.closed = true;
	st.cache_height = -1;
	pplns_save_state(coind->id, st);
	stratumlog("pplns: %s no PROP share left to pay after id %lld; PPLNS from now on\n", coind->symbol, st.floor_id);
}

// The split for a new template, or NULL for an old-style (legacy) coinbase.
static PPLNS_SPLIT *pplns_compute(YAAMP_COIND *coind, YAAMP_JOB_TEMPLATE *templ, json_int_t available)
{
	PPLNS_STATE &st = pplns_state(coind->id);
	if(!pplns_take_over(coind, st)) return NULL;

	int mode = st.closed? PPLNS_MODE_PPLNS: PPLNS_MODE_CLOSE;

	uint64_t coin_target = decode_compact(templ->nbits);
	if(templ->nbits[0] && !coin_target) coin_target = 0xFFFF000000000000ULL;
	double netdiff = target_to_diff(coin_target);
	double window = g_pplns.window * netdiff;

	PPLNS_SPLIT *split = new PPLNS_SPLIT;
	split->mode = mode;
	split->coinid = coind->id;

	bool fresh = st.cache_height == templ->height && st.cache_mode == mode && st.cache_time + g_pplns.refresh > time(NULL);
	if(fresh) {
		split->payees = st.cache;
		split->share_from = st.cache_from;
		split->share_to = st.cache_to;
		split->window = st.cache_window;
	}
	else {
		bool ok = mode == PPLNS_MODE_CLOSE? read_close(coind, st, split): read_window(coind, st, split, window);
		if(ok && mode == PPLNS_MODE_CLOSE && split->payees.empty()) {
			// no share left to pay with PROP: nothing to close
			pplns_close_empty(coind, st);
			mode = split->mode = PPLNS_MODE_PPLNS;
			ok = read_window(coind, st, split, window);
		}
		if(!ok) {
			delete split;
			return NULL;
		}
		st.cache = split->payees;
		st.cache_from = split->share_from;
		st.cache_to = split->share_to;
		st.cache_window = split->window;
		st.cache_height = split->payees.empty()? -1: templ->height;
		st.cache_mode = mode;
		st.cache_time = time(NULL);
	}

	split_amounts(coind, split, available);
	return split;
}

///////////////////////////////////////////////////////////////////////////////////////////////

void pplns_coinbase(YAAMP_COIND *coind, YAAMP_JOB_TEMPLATE *templ, json_int_t available, const char *commitment)
{
	PPLNS_TEMPL *pt = new PPLNS_TEMPL;
	pthread_mutex_init(&pt->mutex, 0);
	pt->head = templ->coinb2;
	pt->commitment = commitment? commitment: "";
	pt->available = available;
	pt->split = NULL;
	templ->pplns = pt;

	PPLNS_SPLIT *split = NULL;
	if(g_pplns.enable)
	{
		CommonLock(&g_pplns_mutex);
		pplns_check_tables();
		if(g_pplns.enable) split = pplns_compute(coind, templ, available);
		CommonUnlock(&g_pplns_mutex);
	}

	std::string outputs = split? outputs_split(coind, pt, split): outputs_classic(coind, pt);
	// keep mining.notify well under its 32KB buffer
	if(split && pt->head.size() + outputs.size() > 20000) {
		stratumlog("pplns: %s coinbase too big (%d outputs), everyone is paid the normal way this time\n",
			coind->symbol, (int) split->payees.size());
		split_all_normal(split);
		outputs = outputs_classic(coind, pt);
	}

	strcpy(templ->coinb2, (pt->head + outputs).c_str());
	pt->split = split;
}

///////////////////////////////////////////////////////////////////////////////////////////////
// checking the coinbase with the node before miners get it

static bool proposal_supported(YAAMP_COIND *coind)
{
	if(!strcmp(coind->rpcencoding, "DCR")) return false;
	if(coind->pos || coind->usegetwork) return false;
	if(!strcmp(g_stratum_algo, "lbry") || !strcmp(g_stratum_algo, "jha") || !strcmp(g_stratum_algo, "sha256csm")) return false;
	return true;
}

// Returns NULL if the node accepts the block, else its reason (static buffer).
static const char *pplns_proposal(YAAMP_COIND *coind, YAAMP_JOB_TEMPLATE *templ)
{
	static char reason[256];

	YAAMP_JOB_VALUES *v = new YAAMP_JOB_VALUES;
	memset(v, 0, sizeof(YAAMP_JOB_VALUES));
	build_submit_values(v, templ, "00000000", "00000000", templ->ntime, "00000000", 0, 0, templ->coinb2);

	size_t size = strlen(v->coinbase) + 1024;
	for(size_t i = 0; i < templ->txdata.size(); i++) size += templ->txdata[i].size();
	if(coind->usemweb) size += strlen(templ->mweb);

	char *block = (char *) malloc(size);
	char *params = (char *) malloc(size + 128);
	if(!block || !params) {
		free(block); free(params); delete v;
		return NULL;	// can't check: keep it
	}

	char count[16];
	if(templ->txcount <= 252) sprintf(count, "%02x", templ->txcount & 0xff);
	else sprintf(count, "fd%02x%02x", templ->txcount & 0xff, templ->txcount >> 8);

	std::string coinbase = v->coinbase;
	if(templ->has_segwit_txs && coinbase.size() > 16) {
		// with a witness commitment, the node wants the coinbase witness (submitblock adds it itself)
		coinbase = coinbase.substr(0, 8) + "0001" + coinbase.substr(8, coinbase.size() - 16) +
			"0120" + std::string(64, '0') + coinbase.substr(coinbase.size() - 8);
	}

	sprintf(block, "%s%s%s", v->header_be, count, coinbase.c_str());
	for(size_t i = 0; i < templ->txdata.size(); i++) strcat(block, templ->txdata[i].c_str());
	if(coind->usemweb) strcat(block, templ->mweb);
	delete v;

	sprintf(params, "[{\"mode\":\"proposal\",\"data\":\"%s\"}]", block);
	free(block);

	json_value *json = rpc_call(&coind->rpc, "getblocktemplate", params);
	free(params);
	if(!json) {
		strcpy(reason, "no answer from the node");
		return reason;
	}

	const char *ret = NULL;
	json_value *result = json_get_object(json, "result");
	json_value *error = json_get_object(json, "error");
	if(error && error->type != json_null) {
		const char *msg = json_get_string(error, "message");
		snprintf(reason, sizeof(reason), "%s", msg? msg: "error");
		ret = reason;
	}
	else if(result && result->type == json_string) {
		snprintf(reason, sizeof(reason), "%s", result->u.string.ptr);
		ret = reason;
	}
	json_value_free(json);
	return ret;
}

void pplns_check_template(YAAMP_COIND *coind, YAAMP_JOB_TEMPLATE *templ)
{
	PPLNS_TEMPL *pt = templ->pplns;
	if(!pt || !pt->split || pt->split->fallback) return;
	if(!g_pplns.check_template || !proposal_supported(coind)) return;

	bool any = false;
	for(size_t i = 0; i < pt->split->payees.size(); i++)
		if(pt->split->payees[i].in_coinbase) any = true;
	if(!any) return;	// same outputs as the usual coinbase

	// same coinbase as the last one the node accepted for this height: no need to ask again
	static std::string last_ok;
	static int last_ok_height = -1;
	static pthread_mutex_t last_mutex = PTHREAD_MUTEX_INITIALIZER;
	CommonLock(&last_mutex);
	bool known = last_ok_height == templ->height && last_ok == templ->coinb2;
	CommonUnlock(&last_mutex);
	if(known) return;

	const char *reason = pplns_proposal(coind, templ);
	if(!reason) {
		CommonLock(&last_mutex);
		last_ok = templ->coinb2;
		last_ok_height = templ->height;
		CommonUnlock(&last_mutex);
		return;
	}

	stratumlog("pplns: %s %d node refused the coinbase (%s), everyone is paid the normal way for this template\n",
		coind->symbol, templ->height, reason);

	split_all_normal(pt->split);
	strcpy(templ->coinb2, (pt->head + outputs_classic(coind, pt)).c_str());
}

///////////////////////////////////////////////////////////////////////////////////////////////
// solo

static bool password_option(const char *password, const char *option)
{
	// options are separated by commas, e.g. "c=DMD,m=solo,d=65536"
	char buf[1024];
	snprintf(buf, sizeof(buf), "%s", password);
	for(char *tok = strtok(buf, ",; "); tok; tok = strtok(NULL, ",; "))
		if(!strcasecmp(tok, option)) return true;
	return false;
}

bool pplns_client_solo(YAAMP_CLIENT *client, YAAMP_COIND *coind)
{
	client->solo = false;
	if(!password_option(client->password, "m=solo")) return false;

	if(!g_pplns.enable || !g_pplns.solo || !coind) {
		clientlog(client, "solo mining is not available on this port, mining with the pool");
		return false;
	}

	if(address_script(coind, client->username).empty()) {
		clientlog(client, "solo refused: %s can't be paid in the coinbase, mining with the pool", client->username);
		return false;
	}

	client->pplns_nofees = false;
	client->pplns_donation = 0;
	CommonLock(&g_pplns_mutex);
	MYSQL_RES *res = pplns_select("SELECT IFNULL(no_fees,0), IFNULL(donation,0) FROM accounts WHERE id=%d", client->userid);
	if(res) {
		MYSQL_ROW row = mysql_fetch_row(res);
		if(row) {
			client->pplns_nofees = atoi(row[0]) != 0;
			client->pplns_donation = atoi(row[1]);
		}
		mysql_free_result(res);
	}
	CommonUnlock(&g_pplns_mutex);

	client->solo = true;
	if(g_debuglog_client) debuglog("solo miner %s\n", client->username);
	return true;
}

const char *pplns_coinb2(YAAMP_JOB_TEMPLATE *templ, YAAMP_CLIENT *client)
{
	if(!templ) return "";
	PPLNS_TEMPL *pt = templ->pplns;
	if(!pt || !client || !client->solo || !client->userid) return templ->coinb2;

	CommonLock(&pt->mutex);
	std::map<int, PPLNS_SOLO *>::iterator it = pt->solo.find(client->userid);
	if(it != pt->solo.end()) {
		const char *p = it->second->coinb2.c_str();
		CommonUnlock(&pt->mutex);
		return p;
	}

	YAAMP_COIND *coind = (YAAMP_COIND *) object_find(&g_list_coind, client->coinid);
	std::string script = coind? address_script(coind, client->username): "";
	if(script.empty()) {
		CommonUnlock(&pt->mutex);
		client->solo = false;
		clientlog(client, "solo coinbase unavailable, mining with the pool");
		return templ->coinb2;
	}

	PPLNS_SOLO *solo = new PPLNS_SOLO;
	PPLNS_SPLIT &split = solo->split;
	split.mode = PPLNS_MODE_SOLO;
	split.coinid = coind->id;
	split.share_from = split.share_to = 0;
	split.window = 0;
	split.reward = pt->available;
	split.fallback = false;

	PPLNS_PAYEE p;
	p.userid = client->userid;
	p.weight = 1;
	long double net = (long double) pt->available;
	if(!client->pplns_nofees) net = net - net * g_pplns.solo_fee / 100.0;
	if(client->pplns_donation > 0) net = net - net * client->pplns_donation / 100.0;
	p.amount = (json_int_t) floorl(net);
	if(p.amount >= pt->available) p.amount = pt->available - 1;	// the pool wallet keeps an output
	p.in_coinbase = true;
	p.script = script;
	split.payees.push_back(p);
	split.pool_output = pt->available - p.amount;
	split.pool_keep = split.pool_output;

	solo->coinb2 = pt->head + outputs_split(coind, pt, &split);
	pt->solo[client->userid] = solo;

	const char *ret = solo->coinb2.c_str();
	CommonUnlock(&pt->mutex);
	return ret;
}

///////////////////////////////////////////////////////////////////////////////////////////////
// a block was accepted

static void pplns_record(YAAMP_COIND *coind, PPLNS_SPLIT *split, int height, const char *blockhash)
{
	int now = time(NULL);
	char hash[130];
	snprintf(hash, sizeof(hash), "%s", blockhash);
	for(char *c = hash; *c; c++) if(!isxdigit(*c)) { *c = 0; break; }

	std::string sql = "INSERT INTO coinbase_splits (coinid, height, blockhash, mode, userid, amount, in_coinbase, share_from, share_to, time) VALUES ";
	char buf[512];
	snprintf(buf, sizeof(buf), "(%d, %d, '%s', %d, 0, %.8f, 0, %lld, %lld, %d)",
		coind->id, height, hash, split->mode, (double) split->pool_keep / 100000000.0, split->share_from, split->share_to, now);
	sql += buf;
	for(size_t i = 0; i < split->payees.size(); i++)
	{
		PPLNS_PAYEE &p = split->payees[i];
		if(p.amount <= 0) continue;
		snprintf(buf, sizeof(buf), ",(%d, %d, '%s', %d, %d, %.8f, %d, %lld, %lld, %d)",
			coind->id, height, hash, split->mode, p.userid, (double) p.amount / 100000000.0,
			p.in_coinbase? 1: 0, split->share_from, split->share_to, now);
		sql += buf;
	}

	if(!pplns_query("%s", sql.c_str()))
		stratumlog("pplns: ERROR could not record the split of %s block %d %s, credit it by hand\n", coind->symbol, height, hash);
}

void pplns_block_found(YAAMP_COIND *coind, YAAMP_JOB_TEMPLATE *templ, YAAMP_CLIENT *client, int height, const char *blockhash)
{
	if(!g_pplns_ready || !coind || !templ) return;

	PPLNS_TEMPL *pt = templ->pplns;
	PPLNS_SPLIT *split = NULL;
	PPLNS_SOLO *solo = NULL;

	if(pt && client && client->solo) {
		CommonLock(&pt->mutex);
		std::map<int, PPLNS_SOLO *>::iterator it = pt->solo.find(client->userid);
		if(it != pt->solo.end()) solo = it->second;
		CommonUnlock(&pt->mutex);
	}
	if(solo) split = &solo->split;
	else if(pt) split = pt->split;

	// a template made while nobody had shares in the window: its coinbase paid the pool wallet,
	// share the block now, the normal way, so the pool doesn't keep it
	if(split && split->mode != PPLNS_MODE_SOLO && split->payees.empty()) split = NULL;

	CommonLock(&g_pplns_mutex);
	PPLNS_STATE &st = pplns_state(coind->id);

	if(!split && (!g_pplns.enable || !st.taken_over))
	{
		// old-style block: the website pays it with PROP
		PPLNS_SPLIT legacy;
		legacy.mode = PPLNS_MODE_LEGACY;
		legacy.reward = legacy.pool_output = legacy.pool_keep = 0;
		legacy.share_from = legacy.share_to = 0;
		if(g_pplns.enable) pplns_record(coind, &legacy, height, blockhash);
		CommonUnlock(&g_pplns_mutex);
		return;
	}

	PPLNS_SPLIT late;
	if(!split)
	{
		// an old-style template still mined after the switch, or an empty window: shared now,
		// everyone paid the normal way (the coinbase paid the pool wallet)
		late.mode = st.closed? PPLNS_MODE_PPLNS: PPLNS_MODE_CLOSE;
		late.coinid = coind->id;
		bool ok = late.mode == PPLNS_MODE_CLOSE? read_close(coind, st, &late): false;
		if(late.mode == PPLNS_MODE_CLOSE && ok && late.payees.empty()) {
			pplns_close_empty(coind, st);
			late.mode = PPLNS_MODE_PPLNS;
		}
		if(late.mode == PPLNS_MODE_PPLNS) {
			uint64_t coin_target = decode_compact(templ->nbits);
			if(templ->nbits[0] && !coin_target) coin_target = 0xFFFF000000000000ULL;
			ok = read_window(coind, st, &late, g_pplns.window * target_to_diff(coin_target));
		}
		if(ok && late.payees.empty() && client && client->userid > 0) {
			// not even a share written yet: the finder's
			PPLNS_PAYEE p;
			p.userid = client->userid;
			p.weight = 1;
			late.payees.push_back(p);
		}
		if(ok) {
			split_amounts(coind, &late, pt? pt->available: templ->value);
			split_all_normal(&late);
			split = &late;
		}
		else {
			stratumlog("pplns: ERROR could not read the shares for %s block %d, credit it by hand\n", coind->symbol, height);
			CommonUnlock(&g_pplns_mutex);
			return;
		}
	}

	pplns_record(coind, split, height, blockhash);

	if(split->mode == PPLNS_MODE_CLOSE && !st.closed)
	{
		if(split->share_to > st.floor_id) st.floor_id = split->share_to;
		st.closed = true;
		st.cache_height = -1;
		pplns_save_state(coind->id, st);
		stratumlog("pplns: %s block %d closed the PROP round (shares up to id %lld); PPLNS from now on\n",
			coind->symbol, height, st.floor_id);
	}

	int paid = 0;
	for(size_t i = 0; i < split->payees.size(); i++) if(split->payees[i].in_coinbase) paid++;
	debuglog("pplns: %s block %d %s, %d miners, %d paid in the coinbase%s\n", coind->symbol, height, mode_name(split->mode),
		(int) split->payees.size(), paid, split->fallback? " (normal way)": "");

	CommonUnlock(&g_pplns_mutex);
}

void pplns_templ_free(YAAMP_JOB_TEMPLATE *templ)
{
	if(!templ || !templ->pplns) return;
	PPLNS_TEMPL *pt = templ->pplns;
	for(std::map<int, PPLNS_SOLO *>::iterator it = pt->solo.begin(); it != pt->solo.end(); ++it)
		delete it->second;
	delete pt->split;
	pthread_mutex_destroy(&pt->mutex);
	delete pt;
	templ->pplns = NULL;
}
