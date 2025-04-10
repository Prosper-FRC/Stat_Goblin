from flask import Flask, request, jsonify
import mysql.connector
import pandas as pd
import numpy as np
import requests
from sklearn.ensemble import RandomForestRegressor
from sklearn.linear_model import LinearRegression
from sklearn.model_selection import KFold, RandomizedSearchCV
from scipy.stats import randint
import math

app = Flask(__name__)

#############################################
# GLOBAL: TBA API AUTHORIZATION
#############################################
TBA_AUTH_KEY = "iPU2nNv1lDHD3m03JwnaqsCxsJhHLmXuRU0Te4xNoyBvOjxq5nYvsWlpd3bJH0kc"

#############################################
# Database Functions
#############################################
def get_db_connection():
    return mysql.connector.connect(
        host='localhost',
        user='root',
        password='pw123456',
        database='frc_scouting'
    )

def get_unique_events():
    conn = get_db_connection()
    df = pd.read_sql("SELECT DISTINCT event_name FROM scouting_submissions", conn)
    conn.close()
    return df["event_name"].tolist()

def load_historical_data(event_name):
    conn = get_db_connection()
    query = """
        SELECT match_no, robot, alliance, points, action, location, result 
        FROM scouting_submissions
        WHERE event_name = %s
    """
    data = pd.read_sql(query, conn, params=(event_name,))
    conn.close()
    data.columns = data.columns.str.strip()
    return data

#############################################
# Aggregation and Derived Metrics
#############################################
def compute_linear_slope_and_next(xvals, yvals):
    if len(xvals) < 2:
        return 0.0, 0.0
    reg = LinearRegression()
    reg.fit(np.array(xvals).reshape(-1,1), yvals)
    slope = reg.coef_[0]
    intercept = reg.intercept_
    return slope, slope * (max(xvals)+1) + intercept

def get_opponent_defense(row, def_flags):
    match = row["match_no"]
    alliance = str(row["alliance"]).strip().lower()
    if alliance == "blue":
        opp = "red"
    elif alliance == "red":
        opp = "blue"
    else:
        return False
    series = def_flags[(def_flags["match_no"] == match) & (def_flags["alliance_lower"] == opp)]["alliance_defense_flag"]
    return bool(series.iloc[0]) if not series.empty else False

def aggregate_data(data):
    # Group data at the match level:
    match_points_df = (data.groupby(["match_no", "robot", "alliance"])["points"]
                       .sum().reset_index().rename(columns={"points": "match_points"}))
    data['is_scoring'] = data['points'].astype(float) > 0
    defensive_actions_list = {"block", "barge"}
    data['is_defensive'] = data['action'].str.lower().isin(defensive_actions_list)
    
    events_df = (data.groupby(["match_no", "robot", "alliance"])
                .agg(match_events=("result", "count"),
                     scoring_events=("is_scoring", "sum"),
                     defensive_events=("is_defensive", "sum")).reset_index())
    temp_df = (data.assign(is_success=lambda df: df["result"]=="success")
               .groupby(["match_no", "robot", "alliance"]).agg(match_successes=("is_success", "sum")).reset_index())
    
    match_level = pd.merge(match_points_df, events_df, on=["match_no", "robot", "alliance"], how="outer")
    match_level = pd.merge(match_level, temp_df, on=["match_no", "robot", "alliance"], how="outer")
    match_level["match_success_rate"] = (match_level["match_successes"]/match_level["match_events"]).fillna(0.0)
    
    def compute_match_cycle_time(r):
        return 150 / r["scoring_events"] if r["scoring_events"] > 0 else math.inf
    match_level["match_cycle_time"] = match_level.apply(compute_match_cycle_time, axis=1)
    
    # Set opponent defense flag.
    def_flags = (match_level.groupby(["match_no", "alliance"])["defensive_events"]
                 .max().reset_index().rename(columns={"defensive_events": "alliance_defense_flag"}))
    def_flags["alliance_defense_flag"] = def_flags["alliance_defense_flag"] > 0
    def_flags["alliance_lower"] = def_flags["alliance"].apply(lambda x: str(x).lower())
    match_level["opponent_defense_flag"] = match_level.apply(lambda r: get_opponent_defense(r, def_flags), axis=1)
    
    # Overall robot performance
    robot_summary = (match_level.groupby("robot")["match_points"]
                     .agg(total_points="sum", avg_points_per_match="mean", matches="count").reset_index())
    robot_success_rate = (data.groupby("robot")["result"]
                          .apply(lambda x: (x=="success").sum()/len(x) if len(x)>0 else 0)
                          .reset_index(name="success_rate"))
    robot_total_events = data.groupby("robot")["result"].count().reset_index(name="total_events")
    robot_common_action = (data[data["points"].astype(float) > 0]
                           .groupby("robot")["action"]
                           .agg(lambda x: x.mode().iloc[0] if not x.mode().empty else "Unknown").reset_index(name="most_common_action"))
    robot_scoring = data.groupby("robot")["is_scoring"].sum().reset_index(name="total_scoring_events")
    
    robot_perf = pd.merge(robot_summary, robot_success_rate, on="robot", how="outer")
    robot_perf = pd.merge(robot_perf, robot_total_events, on="robot", how="outer")
    robot_perf = pd.merge(robot_perf, robot_common_action, on="robot", how="outer")
    robot_scoring = pd.merge(robot_scoring, robot_perf[["robot", "matches"]], on="robot", how="left")
    
    def compute_baseline_cycle(total_scoring, matches):
        return 150 / (total_scoring/matches) if matches > 0 and total_scoring > 0 else math.inf
    robot_scoring["baseline_cycle"] = robot_scoring.apply(lambda r: compute_baseline_cycle(r["total_scoring_events"], r["matches"]), axis=1)
    robot_perf = pd.merge(robot_perf, robot_scoring[["robot", "total_scoring_events", "baseline_cycle"]], on="robot", how="left")
    
    # Defensive Impact Delta:
    baseline_df = robot_scoring[["robot", "baseline_cycle"]]
    match_level = pd.merge(match_level, baseline_df, on="robot", how="left")
    match_level["def_delta"] = match_level.apply(lambda r: (r["match_cycle_time"] - r["baseline_cycle"]) if r["opponent_defense_flag"] else 0.0, axis=1)
    def avg_def_delta(grp):
        rel = grp[grp["opponent_defense_flag"]]
        return rel["def_delta"].mean() if len(rel) > 0 else 0.0
    defensive_impact = match_level.groupby("robot").apply(avg_def_delta).reset_index(name="defensive_impact_delta")
    robot_perf = pd.merge(robot_perf, defensive_impact, on="robot", how="left")
    robot_perf["defensive_impact_delta"] = robot_perf["defensive_impact_delta"].fillna(0.0)
    
    return robot_perf, match_level

#############################################
# NEW: Compute Detailed Defensive Effects (Defender Perspective)
#############################################
def compute_defensive_effects(match_level, robot_perf):
    records = []
    defense_matches = match_level[match_level["defensive_events"] > 0][["match_no", "robot", "alliance"]].drop_duplicates()
    for idx, row in defense_matches.iterrows():
        match_no = row["match_no"]
        defender = row["robot"]
        alliance = str(row["alliance"]).strip().lower()
        opp_alliance = "red" if alliance == "blue" else "blue" if alliance == "red" else None
        if opp_alliance is None:
            continue
        opp_rows = match_level[(match_level["match_no"] == match_no) & (match_level["alliance"].str.lower() == opp_alliance)]
        if opp_rows.empty:
            continue
        opp_avg_cycle = opp_rows["match_cycle_time"].mean()
        opp_avg_points = opp_rows["match_points"].mean()
        opp_teams = opp_rows["robot"].unique()
        baseline_cycles = []
        baseline_points = []
        for r in opp_teams:
            rp = robot_perf[robot_perf["robot"] == r]
            if not rp.empty:
                baseline_cycles.append(rp.iloc[0]["baseline_cycle"])
                baseline_points.append(rp.iloc[0]["avg_points_per_match"])
        if baseline_cycles and baseline_points:
            opp_baseline_cycle = np.mean(baseline_cycles)
            opp_baseline_points = np.mean(baseline_points)
        else:
            continue
        delta_cycle = opp_avg_cycle - opp_baseline_cycle
        delta_points = opp_avg_points - opp_baseline_points
        records.append({
            "robot": defender,
            "match_no": match_no,
            "delta_cycle": delta_cycle,
            "delta_points": delta_points
        })
    if records:
        df = pd.DataFrame(records)
        effects = df.groupby("robot").agg(def_effect_cycle=("delta_cycle", "mean"),
                                           def_effect_points=("delta_points", "mean")).reset_index()
    else:
        effects = pd.DataFrame(columns=["robot", "def_effect_cycle", "def_effect_points"])
    return effects

#############################################
# STEP 2: Model Training & Helper Functions
#############################################
def train_model(robot_perf):
    X = robot_perf[["robot", "avg_points_per_match", "success_rate", "total_events"]].copy()
    y = robot_perf["total_points"]
    X["robot"] = X["robot"].astype("category").cat.codes
    param_distributions = {
        "n_estimators": randint(100, 201),
        "max_depth": [10, 20, 30, None],
        "min_samples_split": randint(2, 11),
        "min_samples_leaf": randint(1, 5),
        "max_features": ["sqrt", "log2", 0.5],
        "bootstrap": [True, False]
    }
    cv_strategy = KFold(n_splits=3, shuffle=True, random_state=42)
    rf = RandomForestRegressor(random_state=42, n_jobs=-1)
    random_search = RandomizedSearchCV(estimator=rf, param_distributions=param_distributions,
                                       n_iter=20, scoring="neg_mean_squared_error",
                                       cv=cv_strategy, random_state=42, n_jobs=-1, verbose=0)
    random_search.fit(X, y)
    return random_search.best_estimator_

def robot_features_for_model(robot_data):
    df = pd.DataFrame([robot_data], columns=["robot", "avg_points_per_match", "success_rate", "total_events"])
    df["robot"] = df["robot"].astype("category").cat.codes
    return df

def estimate_robot_performance(robot_id, global_avgs):
    return {
        "robot": robot_id,
        "total_points": 0.0,
        "avg_points_per_match": global_avgs["avg_points_per_match"],
        "success_rate": global_avgs["success_rate"],
        "total_events": global_avgs["total_events"],
        "matches": 0,
        "most_common_action": "Unknown",
        "total_scoring_events": 0,
        "baseline_cycle": math.inf,
        "defensive_impact_delta": 0.0
    }

def balanced_robot_prediction(robot_id, robot_perf, best_rf, hist_weight, global_avgs):
    robot_id = str(robot_id).strip()
    if robot_id not in robot_perf["robot"].astype(str).values:
        print(f"Robot {robot_id} missing; using defaults.")
        row = estimate_robot_performance(robot_id, global_avgs)
    else:
        row = robot_perf[robot_perf["robot"].astype(str) == robot_id].iloc[0].to_dict()
    matches = row.get("matches", 0)
    historical_avg = row.get("avg_points_per_match", 0)
    if matches < 1:
        model_pred = historical_avg
    else:
        features = robot_features_for_model(row)
        model_pred_total = best_rf.predict(features)[0]
        model_pred = model_pred_total / matches
    return hist_weight * historical_avg + (1 - hist_weight) * model_pred

#############################################
# STEP 3: TBA Rankings Retrieval via API
#############################################
def get_tba_rankings_api(event_key, auth_key):
    headers = {"X-TBA-Auth-Key": auth_key}
    url = f"https://www.thebluealliance.com/api/v3/event/{event_key}/rankings"
    response = requests.get(url, headers=headers)
    if response.status_code != 200:
        print(f"Error fetching TBA rankings. HTTP {response.status_code}")
        return {}
    data = response.json()
    rankings = {}
    for row in data.get("rankings", []):
        team_key = row.get("team_key", "")
        if team_key:
            team_number = team_key.replace("frc", "")
            rank = row.get("rank")
            rankings[team_number] = rank
    return rankings

#############################################
# STEP 4: Generate Explanation for Alliance Compatibility
#############################################
def generate_candidate_explanation(candidate, candidate_score, candidate_cycle, candidate_action, candidate_rank, 
                                   candidate_def_delta, user_action, user_cycle, user_rank):
    explanation = f"Team {candidate}: "
    if candidate_rank is not None:
        explanation += f"Ranking {candidate_rank}. "
    else:
        explanation += "Ranking unknown. "
    explanation += f"Predicted avg pts/match = {candidate_score:.2f}; Baseline cycle time = {candidate_cycle:.2f} sec. "
    explanation += f"Favorite scoring location: {candidate_action}; "
    if "algae" in candidate_action.lower():
        explanation += "Scores Algae: Yes. "
    else:
        explanation += "Scores Algae: No. "
    if candidate_action.lower() == user_action.lower():
        explanation += "Scoring style similar (risk of redundancy). "
    else:
        explanation += "Scoring style complementary (good coverage). "
    diff_cycle = abs(candidate_cycle - user_cycle)
    if diff_cycle < 0.1:
        explanation += "Cycle time well-matched. "
    else:
        explanation += f"Cycle time differs by {diff_cycle:.2f} sec. "
    if candidate_def_delta > 0:
        explanation += f"Defensive impact: slows opponents by {candidate_def_delta:.2f} sec. "
    return explanation

#############################################
# STEP 5: API Endpoints
#############################################
@app.route("/events", methods=["GET"])
def events_endpoint():
    events = get_unique_events()
    return jsonify({"events": events})

@app.route("/robots", methods=["GET"])
def robots_endpoint():
    event = request.args.get("event")
    if not event:
        return jsonify({"error": "Missing event parameter."}), 400
    data = load_historical_data(event)
    robots = data["robot"].unique().tolist()
    return jsonify({"robots": list(map(str, robots))})

@app.route("/analyze", methods=["GET"])
def analyze():
    try:
        event = request.args.get("event")
        robot = request.args.get("robot")
        event_key = request.args.get("event_key")
        if not event or not robot or not event_key:
            return jsonify({"error": "Missing one or more parameters: event, robot, event_key."}), 400

        data = load_historical_data(event)
        robot_perf, match_level = aggregate_data(data)
        best_rf = train_model(robot_perf)
        global_avgs = {
            "avg_points_per_match": robot_perf["avg_points_per_match"].mean(),
            "success_rate": robot_perf["success_rate"].mean(),
            "total_events": robot_perf["total_events"].mean()
        }
        tba_rankings = get_tba_rankings_api(event_key, TBA_AUTH_KEY)
        user_rank_val = tba_rankings.get(robot)
        if robot not in robot_perf["robot"].astype(str).values:
            return jsonify({"error": f"Robot {robot} not found in aggregated data."}), 400
        user_row = robot_perf[robot_perf["robot"].astype(str) == robot].iloc[0]
        user_most_common_action = user_row["most_common_action"]
        user_cycle = user_row["baseline_cycle"]
        def_effects = compute_defensive_effects(match_level, robot_perf)
        hist_weight = 0.5
        candidate_details = []
        for candidate in robot_perf["robot"].astype(str).values:
            if candidate == robot:
                continue
            candidate_row = robot_perf[robot_perf["robot"].astype(str) == candidate].iloc[0].to_dict()
            cand_score = balanced_robot_prediction(candidate, robot_perf, best_rf, hist_weight, global_avgs)
            cand_cycle = candidate_row.get("baseline_cycle", float('inf'))
            cand_action = candidate_row.get("most_common_action", "Unknown")
            # Cast candidate action to string to avoid errors.
            if "algae" in str(cand_action).lower():
                scores_algae = "Yes"
            else:
                scores_algae = "No"
            cand_rank = tba_rankings.get(candidate)
            cand_def_delta = candidate_row.get("defensive_impact_delta", 0.0)
            def_eff = def_effects[def_effects["robot"] == candidate]
            if not def_eff.empty:
                cand_def_effect_cycle = def_eff.iloc[0]["def_effect_cycle"]
                cand_def_effect_points = def_eff.iloc[0]["def_effect_points"]
            else:
                cand_def_effect_cycle = 0.0
                cand_def_effect_points = 0.0
            explanation = generate_candidate_explanation(
                candidate, cand_score, cand_cycle, cand_action,
                cand_rank, cand_def_delta, user_most_common_action, user_cycle, user_rank_val
            )
            explanation += f"[Defensive Effect: slows opponents by {cand_def_effect_cycle:.2f} sec, reducing pts by {cand_def_effect_points:.2f}]."
            candidate_details.append({
                "robot": candidate,
                "predicted_avg_pts_per_match": cand_score,
                "baseline_cycle": cand_cycle,
                "most_common_action": cand_action,
                "scores_algae": scores_algae,
                "ranking": cand_rank,
                "defensive_impact_delta": cand_def_delta,
                "def_effect_cycle": cand_def_effect_cycle,
                "def_effect_points": cand_def_effect_points,
                "explanation": explanation
            })
        
        # First Pick Options: teams with ranking 1 to 16 (excluding user)
        first_pick_options = [c for c in candidate_details if c.get("ranking") is not None and 1 <= int(c["ranking"]) <= 16]
        first_pick_options.sort(key=lambda x: x["predicted_avg_pts_per_match"], reverse=True)
        
        # Second Pick Options Offense: top 8 from teams with ranking 14 to 30
        second_pick_offense = [c for c in candidate_details if c.get("ranking") is not None and 14 <= int(c["ranking"]) <= 30]
        second_pick_offense.sort(key=lambda x: x["predicted_avg_pts_per_match"], reverse=True)
        second_pick_offense = second_pick_offense[:8]
        
        # Defensive Options: top 8 defensive teams with ranking >= 14 sorted by defensive effect
        defense_options = [c for c in candidate_details if c.get("ranking") is not None and int(c["ranking"]) >= 14]
        defense_options.sort(key=lambda x: x["def_effect_cycle"], reverse=True)
        defense_options = defense_options[:8]
        
        output = {
            "user_robot": robot,
            "user_rank": user_rank_val,
            "first_pick_options": first_pick_options,
            "second_pick_options_offense": second_pick_offense,
            "defensive_options": defense_options,
            "full_candidate_analysis": candidate_details,
            "defensive_effects_summary": def_effects.to_dict(orient="records")
        }
        return jsonify(output)
    except Exception as e:
        app.logger.error("Error in /analyze endpoint", exc_info=True)
        return jsonify({"error": str(e)}), 500

    
    # First Pick Options: all teams ranked 1 to 16 (excluding user)
    first_pick_options = [c for c in candidate_details if c.get("ranking") is not None and 1 <= int(c["ranking"]) <= 16]
    first_pick_options.sort(key=lambda x: x["predicted_avg_pts_per_match"], reverse=True)
    
    # Second Pick Options Offense: top 8 teams ranked 14 to 30
    second_pick_offense = [c for c in candidate_details if c.get("ranking") is not None and 14 <= int(c["ranking"]) <= 30]
    second_pick_offense.sort(key=lambda x: x["predicted_avg_pts_per_match"], reverse=True)
    second_pick_offense = second_pick_offense[:8]
    
    # Defensive Options: top 8 defensive robots ranked 14+
    defense_options = [c for c in candidate_details if c.get("ranking") is not None and int(c["ranking"]) >= 14]
    defense_options.sort(key=lambda x: x["def_effect_cycle"], reverse=True)
    defense_options = defense_options[:8]
    
    output = {
        "user_robot": robot,
        "user_rank": user_rank_val,
        "first_pick_options": first_pick_options,
        "second_pick_options_offense": second_pick_offense,
        "defensive_options": defense_options,
        "full_candidate_analysis": candidate_details,
        "defensive_effects_summary": def_effects.to_dict(orient="records")
    }
    return jsonify(output)

if __name__ == '__main__':
    app.run(port=9105, debug=True)
