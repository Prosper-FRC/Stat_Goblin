#!/usr/bin/env python3
import json
import random
from collections import defaultdict

# Action definitions as provided.
action_definitions = {
    "starting_position_1": {"location": "starting_pad", "points": 0},
    "starting_position_2": {"location": "starting_pad", "points": 0},
    "starting_position_3": {"location": "starting_pad", "points": 0},
    "crosses_starting_line": {"location": "starting_line", "points": 0},
    "auton_left": {"location": "starting_pad", "points": 0},
    "auton_center": {"location": "starting_pad", "points": 0},
    "auton_right": {"location": "starting_pad", "points": 0},
    "picks_up_coral": {"location": "station", "points": 0},
    "scores_coral_level_1": {"location": "reef", "points": 1},
    "scores_coral_level_2": {"location": "reef", "points": 2},
    "scores_coral_level_3": {"location": "reef", "points": 3},
    "scores_coral_level_4": {"location": "reef", "points": 4},
    "picks_up_algae": {"location": "mid_field", "points": 0},
    "scores_algae_net": {"location": "net", "points": 4},

     "plays_defense": { "location": "opponent_side", "points": 0 },
     "attempts_to_steal": { "location": "opponent_reef", "points": 0 },
     "disabled": { "location": "anywhere", "points": 0 },

    "scores_algae_processor": {"location": "processor", "points": 6},
    "attempts_shallow_climb": {"location": "barge", "points": 6},
    "attempts_deep_climb": {"location": "barge", "points": 12}
}

# Pools for teleop events and auton moves.
red_teleop_pool = ["picks_up_coral", "scores_coral_level_1", "scores_coral_level_2", "plays_defense", "plays_defense", "scores_coral_level_3", "scores_coral_level_4", "picks_up_algae", "scores_algae_net", "scores_algae_processor"]
blue_teleop_pool = ["picks_up_coral", "scores_coral_level_1", "scores_coral_level_2", "plays_defense", "plays_defense", "plays_defense", "scores_coral_level_3", "scores_coral_level_4", "picks_up_algae", "scores_algae_net", "scores_algae_processor"]

auton_pool = ["auton_left", "auton_center", "auton_right", "picks_up_coral", "scores_coral_level_1", "scores_coral_level_2", "scores_coral_level_3", "scores_coral_level_4", "picks_up_algae", "scores_algae_net", "scores_algae_processor"]

# Constants
IP_ADDRESS = "199.99.00.1"
EVENT_NAME = "battle of Cybertron"
TIMESTAMP_CONST = "2025-02-15 12:00:00"

# Utility function: randomly partition n into k nonnegative parts.
def random_partition(n, k):
    dividers = sorted(random.sample(range(n + 1), k - 1))
    parts = [dividers[0]] + [dividers[i] - dividers[i - 1] for i in range(1, k - 1)] + [n - dividers[-1]]
    return parts

# Utility function: generate a sorted list of n time values between 0 and 150, with first 0 and last 150.
def generate_times(n):
    if n <= 2:
        return [0, 150]
    mid_times = sorted(random.sample(range(1, 150), n - 2))
    return [0] + mid_times + [150]

# Load active_event JSON.
# The JSON structure may contain extra records (header, database, table) so we extract the ones with data.
with open("active_event.json", "r") as f:
    active_json = json.load(f)

# Extract records. They might be under a "data" key.
records = []
for item in active_json:
    if isinstance(item, dict) and "data" in item:
        records.extend(item["data"])
    elif isinstance(item, dict) and "match_number" in item:
        records.append(item)

if not records:
    print("No active event records found.")
    exit(1)

# Group records by match number.
matches = defaultdict(list)
for rec in records:
    # Try "match_number" or "match_no".
    match_val = rec.get("match_number") or rec.get("match_no")
    if match_val is None:
        continue
    try:
        match_no = int(match_val)
    except Exception as e:
        continue
    matches[match_no].append(rec)

# We'll process matches 1 to 40.
match_inserts = []  # will hold tuples for each row.

for match_no in range(1, 41):
    if match_no not in matches:
        continue
    match_records = matches[match_no]
    # Group by alliance.
    alliance_groups = defaultdict(list)
    for rec in match_records:
        alliance = rec.get("alliance")
        if alliance:
            alliance_groups[alliance.capitalize()].append(rec)
    # Sort each alliance group by robot number (assuming robot numbers can be converted to int).
    for alliance in alliance_groups:
        alliance_groups[alliance] = sorted(alliance_groups[alliance], key=lambda r: int(r.get("robot", "0")))
    # Combine robots in order: first Red then Blue.
    all_robots = []
    for alliance in ["Red", "Blue"]:
        all_robots.extend(alliance_groups.get(alliance, []))
    
    if not all_robots:
        continue

    # Determine total events for this match: random number between 85 and 140.
    total_events = random.randint(85, 140)
    base_events = 4  # each robot gets at least 4 events (starting, crosses, auton, climb)
    remainder = total_events - len(all_robots) * base_events
    # Partition remainder among the robots.
    extra_events = random_partition(remainder, len(all_robots))
    robot_events_counts = [base_events + extra for extra in extra_events]
    
    # For each robot, generate its event sequence.
    for i, rec in enumerate(all_robots):
        robot = rec.get("robot")
        alliance = rec.get("alliance").capitalize()
        events_count = robot_events_counts[i]
        times = generate_times(events_count)
        
        # Determine starting position based on the robot's order within its alliance.
        if alliance == "Red":
            red_list = alliance_groups["Red"]
            pos_index = red_list.index(rec)  # 0,1,2 expected.
        else:
            blue_list = alliance_groups["Blue"]
            pos_index = blue_list.index(rec)
        starting_action = f"starting_position_{pos_index + 1}"
        
        # Determine climb action and its success probability.
        if pos_index + 1 in [1, 3]:
            climb_action = "attempts_deep_climb"
            climb_success_prob = 0.8
        else:
            climb_action = "attempts_shallow_climb"
            climb_success_prob = 0.5
        
        # Build events sequence:
        events = []
        # First three fixed events.
        events.append(starting_action)
        events.append("crosses_starting_line")
        events.append(random.choice(auton_pool))
        # Middle teleop events: count = events_count - 4 (reserve last for climb).
        teleop_count = events_count - 4
        teleop_pool = red_teleop_pool if alliance == "Red" else blue_teleop_pool
        for _ in range(teleop_count):
            events.append(random.choice(teleop_pool))
        # Final event: climb attempt.
        events.append(climb_action)
        
        # For each event, determine result and points.
        for j, action in enumerate(events):
            # Fixed events (first three) are always success.
            if j < 3:
                result = "success"
            # Final event (climb attempt) uses its specific probability.
            elif j == len(events) - 1:
                result = "success" if random.random() < climb_success_prob else "failure"
            else:
                # Teleop events: 80% chance of success.
                result = "success" if random.random() < 0.8 else "failure"
            base_points = action_definitions.get(action, {"points": 0})["points"]
            points = base_points if result == "success" else 0
            location = action_definitions.get(action, {"location": "unknown"})["location"]
            # Assemble row: (ip_address, event_name, match_no, time_sec, robot, alliance, action, location, result, points, timestamp)
            row = (
                IP_ADDRESS,
                EVENT_NAME,
                match_no,
                times[j],
                robot,
                alliance,
                action,
                location,
                result,
                points,
                TIMESTAMP_CONST
            )
            match_inserts.append(row)

# Generate a MySQL INSERT statement for all rows.
# Column order: ip_address, event_name, match_no, time_sec, robot, alliance, action, location, result, points, timestamp
print("INSERT INTO scouting_submissions (ip_address, event_name, match_no, time_sec, robot, alliance, action, location, result, points, timestamp) VALUES")
values_list = []
for row in match_inserts:
    ip, evname, match_no, time_sec, robot, alliance, action, location, result, points, ts = row
    values_list.append(
        f"('{ip}','{evname}',{match_no},{time_sec},'{robot}','{alliance}','{action}','{location}','{result}',{points},'{ts}')"
    )
print(",\n".join(values_list) + ";")
