"""
Scoring Formula Script
    Defines a Scoring formula. Gets Parameters and jobs from Formula Libraries in libs folder or contains new ones.
    Name of primary functions has to be mantained:
        - process_result : jobs done of Flight_result obj. before scoring
        - points_allocation : main function called to calculate scoring
    Defines which classes formula applies
    Defines standard parameters values for each class
"""
from formula import FormulaPreset, Preset
from formulas.libs.pwc import *

''' Formula Info'''
# Formula Name: usually the filename in capital letters
formula_name = 'PWC2016'
# Formula Type: pwc, gap, aat, any formula in libs folder
formula_type = 'pwc'
# Formula Version: INT, usually identified with year
formula_version = '2016'
# Comp Class: PG, HG, BOTH
formula_class = 'PG'

''' Default Formula presets
    pg_preset: PG default values, if formula applies for PG or mixed
    hg_preset: HG default values, if formula applies for HG or mixed'''
# TODO should have switch for each parameter to be editable or not in frontend

pg_preset = FormulaPreset(
    # This part should not be edited
    formula_name=Preset(value=formula_name, visible=True, editable=True),
    formula_type=Preset(value=formula_type, visible=True),
    formula_version=Preset(value=formula_version, visible=True),

    # Editable part starts here
    # Distance Points: on, difficulty, off
    formula_distance=Preset(value='on', visible=False),
    # Arrival Points: position, time, off
    formula_arrival=Preset(value='off', visible=False),
    # Departure Points: on, leadout, off
    formula_departure=Preset(value='leadout', visible=False),
    # Lead Factor: factor for Leadou Points calculation formula
    lead_factor=Preset(value=1.0, visible=False),
    # Squared Distances used for LeadCoeff: factor for Leadou Points calculation formula
    # lead_squared_distance=Preset(value=False, visible=False),
    # Time Points: on, off
    formula_time=Preset(value='on', visible=False),
    # Arrival Altitude Bonus: Bonus points factor on ESS altitude
    arr_alt_bonus=Preset(value=0, visible=False),
    # ESS Min Altitude
    arr_min_height=Preset(value=None, visible=False),
    # ESS Max Altitude
    arr_max_height=Preset(value=None, visible=False),
    # Minimum flight time for task validation (minutes)
    validity_min_time=Preset(value=3600, visible=True, editable=True),
    # Score back time for Stopped Tasks (minutes)
    score_back_time=Preset(value=300, visible=True, editable=True),
    # Max allowed Jump the Gun (seconds)
    max_JTG=Preset(value=0, visible=False),
    # Penalty per Jump the Gun second
    JTG_penalty_per_sec=Preset(value=None, visible=False),
    # Type of Total Validity: ftv, all
    overall_validity=Preset(value='ftv', visible=True, editable=True),
    # FTV Parameter
    validity_param=Preset(value=0.75, visible=True, editable=True),
    # Penalty when ESS but not Goal: default is 1 for PG and 0.2 for HG
    no_goal_penalty=Preset(value=1.0, visible=False),
    # Glide Bonus for Stopped Task: default is 4 for PG and 5 for HG
    glide_bonus=Preset(value=4.0, visible=False),
    # Waypoint radius tolerance for validation: FLOAT default is 0.1%
    tolerance=Preset(value=0.005, visible=True, editable=True),
    # Waypoint radius minimum tolerance (meters): INT default = 5
    min_tolerance=Preset(value=5, visible=True, editable=True),
    # Scoring Altitude Type: default is GPS for PG and QNH for HG
    scoring_altitude=Preset(value='GPS', visible=True, editable=True)
)


def calculate_results(task):
    """ Method to get to final results:
            Task validity calculation: day_quality(task);
            Points Weights calculation: points_weight(task);
            Points Allocation: points_allocation(task);
        Methods that are not on the script, are recalled from main library (pwc or gap) """

    # dist_validity, time_validity, launch_validity, stop_validity, day_quality
    day_quality(task)

    # avail_dist_points, avail_time_points, avail_dep_points, avail_arr_points
    points_weight(task)

    # points allocation to pilots
    points_allocation(task)
