
#include "stratum.h"

double client_normalize_difficulty(double difficulty)
{
	if(difficulty < g_stratum_min_diff) difficulty = g_stratum_min_diff;
	else if(difficulty < 1) difficulty = floor(difficulty*1000/2)/1000*2;
	else if(difficulty > 1) difficulty = floor(difficulty/2)*2;
	if(difficulty > g_stratum_max_diff) difficulty = g_stratum_max_diff;
	return difficulty;
}

void client_record_difficulty(YAAMP_CLIENT *client)
{
	if(client->difficulty_remote)
	{
		client->last_submit_time = current_timestamp();
		return;
	}

	int e = current_timestamp() - client->last_submit_time;
	if(e < 500) e = 500;
	int p = 5;

	client->shares_per_minute = (client->shares_per_minute * (100 - p) + 60*1000*p/e) / 100;
	client->last_submit_time = current_timestamp();

//	debuglog("client->shares_per_minute %f\n", client->shares_per_minute);
}

void client_change_difficulty(YAAMP_CLIENT *client, double difficulty)
{
	if(difficulty <= 0) return;

	difficulty = client_normalize_difficulty(difficulty);
	// never below what the miner asked for (mining.suggest_difficulty, minimum-difficulty)
	if(client->difficulty_floor > 0 && difficulty < client->difficulty_floor)
		difficulty = client->difficulty_floor;
	if(difficulty <= 0) return;

//	debuglog("change diff to %f %f\n", difficulty, client->difficulty_actual);
	if(difficulty == client->difficulty_actual) return;

	// shares of the jobs already sent stay valid at the difficulty they were sent with
	// (two changes before the next job: those jobs still had the first difficulty)
	if(!client->diff_change_jobid || client->diff_change_jobid != client->jobid_sent)
	{
		client->difficulty_prev = client->difficulty_actual;
		client->diff_change_jobid = client->jobid_sent;
	}

	client->difficulty_actual = difficulty;
	client_send_difficulty(client, difficulty);
}

// The difficulty a share of this job is checked and credited at. For a job sent before the last
// change, the lower of the two: some miners apply a new difficulty to the work they already have.
double client_job_difficulty(YAAMP_CLIENT *client, int jobid)
{
	if(client->difficulty_prev > 0 && client->diff_change_jobid && jobid <= client->diff_change_jobid)
		return client->difficulty_prev < client->difficulty_actual? client->difficulty_prev: client->difficulty_actual;
	return client->difficulty_actual;
}

void client_adjust_difficulty(YAAMP_CLIENT *client)
{
	if(client->difficulty_remote) {
		client_change_difficulty(client, client->difficulty_remote);
		return;
	}

	if(client->shares_per_minute > 100)
		client_change_difficulty(client, client->difficulty_actual*4);

	else if(client->difficulty_fixed)
		return;

	else if(client->shares_per_minute > 75)
		client_change_difficulty(client, client->difficulty_actual*3.5);

	else if(client->shares_per_minute > 50)
		client_change_difficulty(client, client->difficulty_actual*3);

	else if(client->shares_per_minute > 25)
		client_change_difficulty(client, client->difficulty_actual*2);

	else if(client->shares_per_minute > 20)
		client_change_difficulty(client, client->difficulty_actual*1.5);

	else if(client->shares_per_minute <  5)
		client_change_difficulty(client, client->difficulty_actual/2);
}

int client_send_difficulty(YAAMP_CLIENT *client, double difficulty)
{
//	debuglog("%s diff %f\n", client->sock->ip, difficulty);
	client->shares_per_minute = YAAMP_SHAREPERSEC;

	if(difficulty >= 1)
		client_call(client, "mining.set_difficulty", "[%.0f]", difficulty);
	else
		client_call(client, "mining.set_difficulty", "[%0.8f]", difficulty);
	return 0;
}

void client_initialize_difficulty(YAAMP_CLIENT *client)
{
	char *p = strstr(client->password, "d=");
	char *p2 = strstr(client->password, "decred=");
	if(!p || p2) return;

	double diff = client_normalize_difficulty(atof(p+2));
	uint64_t user_target = diff_to_target(diff);

//	debuglog("%016llx target\n", user_target);
	// (no upper limit but the port's diff_max: SHA256 ASICs need more than 131070)
	if(user_target > 0 && user_target <= YAAMP_MAXDIFF)
	{
		client->difficulty_actual = diff;
		client->difficulty_fixed = true;
	}

}






