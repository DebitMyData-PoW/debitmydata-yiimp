// PPLNS paid in the coinbase, with optional solo mining (password m=solo).
//
// Each block's coinbase pays the miners of the last N shares directly (N = window x network
// difficulty). Miners whose part is under min_output, or beyond the max_outputs biggest, are
// paid the normal way (their part goes to the pool output and becomes an earning).
//
// The switch from PROP is automatic and pays nothing twice:
//   LEGACY  until the website has paid every old block: today's coinbase, the website pays PROP.
//   CLOSE   the next block closes the PROP round: every share not paid yet, paid by this block.
//   PPLNS   from then on.
// A solo miner gets the whole block it finds, minus solo_fee, in a coinbase of its own.
//
// Every block found records its split in the coinbase_splits table; the website credits it.

#ifndef PPLNS_H
#define PPLNS_H


#define PPLNS_MODE_LEGACY	0
#define PPLNS_MODE_CLOSE	1
#define PPLNS_MODE_PPLNS	2
#define PPLNS_MODE_SOLO		3

struct PPLNS_CONFIG
{
	bool enable;
	double window;		// window size, in network difficulties (2 = 2x)
	double fee;		// pool fee, percent of each miner's part
	bool solo;		// allow m=solo in the password
	double solo_fee;	// percent
	int max_outputs;	// miner outputs in a coinbase
	double min_output;	// coins; smaller parts are paid the normal way
	int refresh;		// seconds before the share window is read again
	bool check_template;	// check each new coinbase with getblocktemplate "proposal"
	int wait_legacy;	// seconds to wait after the last old-style block before switching
};

extern PPLNS_CONFIG g_pplns;
extern bool g_shares_solo_column;

struct PPLNS_PAYEE
{
	int userid;
	double weight;		// difficulty counted in the window
	json_int_t amount;	// net amount, in the coin's smallest unit
	bool in_coinbase;
	std::string script;	// scriptPubKey (hex) when paid in the coinbase
};

struct PPLNS_SPLIT
{
	int mode;
	int coinid;
	json_int_t reward;		// everything shared out (the block's coinbase value)
	json_int_t pool_output;		// the pool wallet's output
	json_int_t pool_keep;		// fees and rounding, kept by the pool
	long long share_from;		// first share id counted
	long long share_to;		// last share id counted
	double window;			// difficulty counted
	bool fallback;			// the node refused this coinbase: everyone is paid the normal way
	std::vector<PPLNS_PAYEE> payees;
};

struct PPLNS_SOLO
{
	PPLNS_SPLIT split;
	std::string coinb2;
};

// Per job template (allocated after the template's memset).
struct PPLNS_TEMPL
{
	pthread_mutex_t mutex;
	std::string head;		// coinb2 before the outputs (script end and input sequence)
	std::string commitment;		// segwit commitment output, or empty
	json_int_t available;
	PPLNS_SPLIT *split;		// NULL in legacy mode
	std::map<int, PPLNS_SOLO *> solo;	// by userid
};

class YAAMP_COIND;
class YAAMP_CLIENT;
struct YAAMP_JOB_TEMPLATE;

void pplns_init(dictionary *ini);
void pplns_startup();

// coinbase.cpp
void coinbase_pack_pool_output(YAAMP_COIND *coind, char *data, json_int_t amount);

// coinbase.cpp: builds the outputs and the locktime at the end of templ->coinb2.
void pplns_coinbase(YAAMP_COIND *coind, YAAMP_JOB_TEMPLATE *templ, json_int_t available, const char *commitment);

// coind_template.cpp: asks the node to check the new coinbase; falls back if it refuses.
void pplns_check_template(YAAMP_COIND *coind, YAAMP_JOB_TEMPLATE *templ);

// The coinbase part 2 to send to this client (its own for a solo miner).
const char *pplns_coinb2(YAAMP_JOB_TEMPLATE *templ, YAAMP_CLIENT *client);

// client_submit.cpp: a block was accepted; records the split.
void pplns_block_found(YAAMP_COIND *coind, YAAMP_JOB_TEMPLATE *templ, YAAMP_CLIENT *client, int height, const char *blockhash);

// authorize: m=solo in the password, if solo is allowed and the address can be paid.
bool pplns_client_solo(YAAMP_CLIENT *client, YAAMP_COIND *coind);

void pplns_templ_free(YAAMP_JOB_TEMPLATE *templ);

#endif
