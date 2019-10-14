<?php
require 'startup.php';
#require_once LIBDIR.'olc.php';
#require_once LIBDIR.'team.php';

function overall_handicap($link, $comPk, $how, $param, $cls)
{
    $sql = "SELECT
                `T`.`tasPk`,
                MAX(`TR`.`tarScore`) AS `maxScore`
            FROM
                `tblTask` `T`,
                `tblTaskResult` `TR`
            WHERE
                `T`.`tasPk` = `TR`.`tasPk` AND `T`.`comPk` = $comPk
            GROUP BY
                `T`.`tasPk`";
    $result = mysqli_query($link, $sql) or die('Error ' . mysqli_errno($link) . ' Handicap maxscore failed: ' . mysqli_connect_error());
    $maxarr = [];
    while ($row = mysqli_fetch_array($result, MYSQLI_BOTH))
    {
        $maxarr[$row['tasPk']] = $row['maxScore'];
    }

    $sql = "select
                P.*,
                TK.*,
                TR.*,
                H.*
            from
                tblTaskResult TR,
                tblTask TK,
                tblTrack K,
                PilotView P,
                tblHandicap H,
                tblCompetition C
            where
                H.comPk = C.comPk
                and C.comPk = TK.comPk
                and K.traPk = TR.traPk
                and K.pilPk = P.pilPk
                and H.pilPk = P.pilPk
                and H.comPk = TK.comPk
                and TK.comPk = $comPk
                and TR.tasPk = TK.tasPk
            order by
                P.pilPk,
                TK.tasPk";
    #$sql = "select TK.*,TR.*,P.* from tblTaskResult TR, tblTask TK, tblTrack T, PilotView P, tblCompetition C where C.comPk=$comPk and TK.comPk=C.comPk and TK.tasPk=TR.tasPk and TR.traPk=T.traPk and T.traPk=TR.traPk and P.pilPk=T.pilPk $cls order by P.pilPk, TK.tasPk";

    $result = mysqli_query($link, $sql) or die('Error ' . mysqli_errno($link) . ' Task result query failed: ' . mysqli_connect_error());
    $results = [];
    while ($row = mysqli_fetch_array($result, MYSQLI_BOTH))
    {
        $tasPk = $row['tasPk'];
        $score = round($row['tarScore'] - $row['hanHandicap'] * $maxarr[$tasPk]);
        if ($row['tarResultType'] == 'abs' || $row['tarResultType'] == 'dnf')
        {
            $score = 0;
        }
        $validity = $row['tasQuality'] * 1000;
        $pilPk = $row['pilPk'];
        $tasName = $row['tasName'];

        if (!$results[$pilPk])
        {
            $results[$pilPk] = [];
            $results[$pilPk]['name'] = $row['pilFirstName'] . ' ' . $row['pilLastName'];
        }
        //echo "pilPk=$pilPk tasname=$tasName, result=$score<br>\n";
        $perf = 0;
        if ($how == 'ftv')
        {
            $perf = 0;
            if ($validity > 0)
            {
                $perf = round($score / $validity, 3) * 1000;
            }
        }
        else
        {
            $perf = round($score, 0);
        }
        $results[$pilPk]["${perf}${tasName}"] = array('score' => $score, 'validity' => $validity, 'tname' => $tasName);
    }

    return filter_results($comPk, $how, $param, $results);
}

function comp_result($link, $comPk, $how, $param, $cls, $tasktot, $ext)
{
    # $sql = "select TK.*,TR.*,P.*,T.traGlider from tblTaskResult TR, tblTask TK, tblTrack T, PilotView P, tblCompetition C where C.comPk=$comPk and TK.comPk=C.comPk and TK.tasPk=TR.tasPk and TR.traPk=T.traPk and T.traPk=TR.traPk and P.pilPk=T.pilPk $cls order by P.pilPk, TK.tasPk";
    # New sql adding MaxScore for PWC FTV Calc

    $extsql = "SELECT
                    TK.*,
                    TR.*,
                    (
                        SELECT
                            MAX(ResultView.tarScore)
                        FROM
                            ResultView
                        WHERE
                            ResultView.tasPk = TK.tasPk
                    ) AS maxScore,
                    P.*,
                    (
                        SELECT
                            C.natIso3
                        FROM
                            tblCountryCodes C
                        WHERE
                            C.natID = P.pilNat
                    ) AS pilNationCode,
                    TR.traGlider
                FROM
                    tblCompetition C
                    JOIN tblTask TK ON TK.comPk = C.comPk
                    JOIN ResultView TR ON TK.tasPk = TR.tasPk
                    JOIN PilotView P ON P.pilPk = TR.pilPk
                    JOIN tblForComp FC ON FC.comPk = C.comPk
                WHERE
                    C.comPk = $comPk
                ORDER BY
                    P.pilPk,
                    TK.tasPk";


    // $sql = "    SELECT
    //                 TK.*,
    //                 TR.*,
    //                 (
    //                     SELECT
    //                         MAX(ResultView.tarScore)
    //                     FROM
    //                         ResultView
    //                     WHERE
    //                         ResultView.tasPk = TK.tasPk
    //                 ) AS maxScore,
    //                 F.forClass,
    //                 F.forVersion,
    //                 P.*,
    //                 (
    //                     SELECT
    //                         C.natIso3
    //                     FROM
    //                         tblCountryCodes C
    //                     WHERE
    //                         C.natID = P.pilNat
    //                 ) AS pilNationCode,
    //                 TR.traGlider
    //             FROM
    //                 tblCompetition C
    //                 JOIN tblTask TK ON TK.comPk = C.comPk
    //                 JOIN ResultView TR ON TK.tasPk = TR.tasPk
    //                 JOIN PilotView P ON P.pilPk = TR.pilPk
    //                 JOIN tblForComp FC ON FC.comPk = C.comPk
    //                 JOIN tblFormula F ON F.forPk = FC.forPk
    //
    //             WHERE
    //                 C.comPk = $comPk $cls
    //             ORDER BY
    //                 P.pilPk,
    //                 TK.tasPk";
    $sql = "    SELECT
                    `TK`.`tasName`,
                    `TK`.`tasQuality`,
                    (
                        SELECT
                            MAX(`ResultView`.`tarScore`)
                        FROM
                            `ResultView`
                        WHERE
                            `ResultView`.`tasPk` = `TK`.`tasPk`
                    ) AS `maxScore`,
                    `C`.`forClass`,
                    `C`.`forVersion`,
                    `TR`.`tarScore`,
                    `TR`.`pilPk`,
                    `TR`.`pilName`,
                    `TR`.`pilNationCode`,
                    `TR`.`pilSponsor`,
                    `TR`.`traGlider`,
                    `TR`.`traDHV`,
                    `P`.`pilFAI`,
                    `P`.`pilCIVL`
                FROM
                	`CompetitionView` `C`
                    JOIN `TaskView` `TK` USING(`comPk`)
                    JOIN `ResultView` `TR` USING(`tasPk`)
                    JOIN `PilotView` `P` USING(`pilPk`)
                WHERE
                    `C`.`comPk` = $comPk $cls
                ORDER BY
                    `TR`.`pilPk`,
                    `TK`.`tasPk`";
    if ( $ext )
    {
        $sql = $extsql;
    }
    $result = mysqli_query($link, $sql) or die('Error ' . mysqli_errno($link) . ' Task result query failed: ' . mysqli_connect_error());
    $results = [];
    while ($row = mysqli_fetch_assoc($result))
    {
        $score = round($row['tarScore']);
        $pilPk = $row['pilPk'];
        $tasName = $row['tasName'];
        $nation = $row['pilNationCode'];
        $pilnum = $row['pilFAI'];
        $civlnum = $row['pilCIVL'];
        $glider = ( (stripos($row['traGlider'], 'Unknown') !== false) ? '' : htmlspecialchars(str_replace('\' ', '\'', ucwords(str_replace('\'', '\' ', strtolower(substr($row['traGlider'], 0, 25)))))) );
        $sponsor = isset($row['pilSponsor']) ? htmlspecialchars(str_replace('\' ', '\'', ucwords(str_replace('\'', '\' ', strtolower(substr($row['pilSponsor'], 0, 40)))))) : '';
        $gender = $row['pilSex'];
        $maxScore = $row['maxScore'];
        $formula = $row['forClass'];
        if ( $formula = 'pwc' ) # calculates FTV parameters based on winner score (PWC)
        {
            $validity = $row['maxScore'];
        }
        else
        {
            $validity = $row['tasQuality'] * 1000;
        }


        if (!array_key_exists($pilPk,$results) || !$results[$pilPk])
        {
            $results[$pilPk] = [];
            //$results[$pilPk]['name'] = $row['pilFirstName'] . ' ' . $row['pilLastName'];
            $results[$pilPk]['name'] = $row['pilName'];
            $results[$pilPk]['hgfa'] = $pilnum;
            $results[$pilPk]['civl'] = $civlnum;
            $results[$pilPk]['nation'] = $nation;
            $results[$pilPk]['glider'] = $glider;
            $results[$pilPk]['sponsor'] = $sponsor;
            $results[$pilPk]['gender'] = $gender;
        }
        //echo "pilPk=$pilPk tasname=$tasName, result=$score<br>\n";
        $perf = 0;
        if ($how == 'ftv')
        {
            $perf = 0;
            if ($validity > 0)
            {
                $perf = round($score / $validity, 3) * 1000;
            }
        }
        else
        {
            $perf = round($score, 0);
        }
        $results[$pilPk]["${perf}${tasName}"] = array('score' => $score, 'validity' => $validity, 'tname' => $tasName);
    }

    if ($how == 'ftv' && $tasktot < 2)
    {
        $param = 1000;
    }

    return filter_results($comPk, $how, $param, $results); # Comp, score Type, score param (FTV perc), results array
}

function filter_results($comPk, $how, $param, $results)
{
    // Do the scoring totals (FTV/X or Y tasks etc)
    $sorted = [];
    foreach ($results as $pil => $arr)
    {
        krsort($arr, SORT_NUMERIC);

        $pilscore = 0;
        if ($how != 'ftv')
        {
            # Max rounds scoring
            $count = 0;
            foreach ($arr as $perf => $taskresult)
            {
                //if ($perf == 'name')
                if (ctype_alpha($perf))
                {
                    continue;
                }
                if ($count < $param)
                {
                    $arr[$perf]['perc'] = 100;
                    $pilscore = $pilscore + $taskresult['score'];
                }
                else
                {
                    $arr[$perf]['perc'] = 0;
                }
                $count++;

            }
        }
        else
        {
            # FTV scoring
            $pilvalid = 0;
            foreach ($arr as $perf => $taskresult)
            {
                //if ($perf == 'name')
                if (ctype_alpha($perf))
                {
                    continue;
                }

                //echo "pil=$pil perf=$perf valid=", $taskresult['validity'], " score=", $taskresult['score'], "<br>";
                if ($pilvalid < $param) # if I still have available validity
                {
                    $gap = $param - $pilvalid;
                    $perc = 0;
                    if ($taskresult['validity'] > 0)
                    {
                        $perc = $gap / $taskresult['validity'];
                    }
                    if ($perc > 1)
                    {
                        $perc = 1;
                    }
                    $pilvalid = $pilvalid + $taskresult['validity'] * $perc;
                    $pilscore = $pilscore + round(($taskresult['score'] * $perc),0);
                    $arr[$perf]['perc'] = $perc * 100;
                    # echo "valid = ".$pilvalid.", score = ".$pilscore.", perc = ".$perc;
                }
            }
        }
        // resort arr by task?
        uasort($arr, "taskcmp");
        #echo "pil=$pil pilscore=$pilscore<br>";
        foreach ($arr as $key => $res)
        {
            #echo "key=$key<br>";
            #if ($key != 'name')
            if (ctype_digit(substr($key,0,1)))
            {
                $arr[$res['tname']] = $res;
                unset($arr[$key]);
            }
        }
        $pilscore = round($pilscore,0);
        $sorted["${pilscore}!${pil}"] = $arr;
    }

    krsort($sorted, SORT_NUMERIC);
    return $sorted;
}

// Main Code Begins HERE //

# Initializing variables to avoid esceptions
# Probably to remove when debugged and fully checked

$overstr = '';

$comPk = intval($_REQUEST['comPk']);

$start = reqival('start');
if ($start < 0)
{
    $start = 0;
}

if (reqexists('class'))
{
    $cval = reqival('class');
}

$file = __FILE__;
$link = db_connect();
$title = 'AirScore'; # default

$query = "  SELECT
                T.*,
                FC.*,
                F.*
            FROM
                tblCompetition T
                JOIN tblForComp FC USING (comPk)
                LEFT OUTER JOIN tblFormula F USING (forPk)
            WHERE
                T.comPk = $comPk";
#echo $query;
$result = mysqli_query($link, $query) or die('Error ' . mysqli_errno($link) . ' Comp query failed: ' . mysqli_connect_error());
$row = mysqli_fetch_assoc($result);
if ($row)
{
    $comName = isset($row['comName']) ? $row['comName'] : '';
    $comExt = $row['comExt'];
    $comExtUrl = $row['comExtUrl'];
    $title = 'AirScore - '.isset($row['comName']) ? $row['comName'] : '';
    $comDateFrom = substr(isset($row['comDateFrom']) ? $row['comDateFrom'] : '',0,10);
    $comDateTo = substr(isset($row['comDateTo']) ? $row['comDateTo'] : '',0,10);
    $comPk = $row['comPk'];
    $comOverall = isset($row['comOverallScore']) ? $row['comOverallScore'] : '';
    $comOverallParam = isset($row['comOverallParam']) ? $row['comOverallParam']*100 : ''; # Discard Parameter, Ex. 75 = 75% eq normal FTV 25%
    $comDirector = isset($row['comMeetDirName']) ? $row['comMeetDirName'] : '';
    $comLocation = isset($row['comLocation']) ? $row['comLocation'] : '';
    if ( isset($row['forClass']) )
    {
        $comFormula = $row['forClass'] . ' ' . ( isset($row['forVersion']) ? $row['forVersion'] : '' );
    }
    elseif ( isset($row['extForName']) )
    {
        $comFormula = $row['extForName'];
    }
    else
    {
        $comFormula = '';
    }
    $forOLCPoints = isset($row['forOLCPoints']) ? $row['forOLCPoints'] : '';
    $comSanction = isset($row['comSanction']) ? $row['comSanction'] : '';
    $comOverall = isset($row['comOverallScore']) ? $row['comOverallScore'] : '';  # Type of scoring discards: FTV, ...
    $comTeamScoring = isset($row['comTeamScoring']) ? $row['comTeamScoring'] : '';
    $comCode = isset($row['comCode']) ? $row['comCode'] : '';
    $comClass = isset($row['comClass']) ? $row['comClass'] : '';
    $comType = isset($row['comType']) ? $row['comType'] : '';
    $forNomGoal = isset($row['forNomGoal']) ? $row['forNomGoal'] : '';
    $forMinDistance = isset($row['forMinDistance']) ? $row['forMinDistance'] : '';
    $forNomDistance = isset($row['forNomDistance']) ? $row['forNomDistance'] : '';
    $forNomTime = isset($row['forNomTime']) ? $row['forNomTime'] : '';
    $forDiscreteClasses = isset($row['forDiscreteClasses']) ? $row['forDiscreteClasses'] : '';
    $claPk = $row['claPk'];
}

# Check if we have a classification request
$sel = get_class_info($link, $comPk);
$classstr = $sel['name'];
$fdhv = $sel['fdhv'];

// $embed = reqsval('embed');

//initializing template header
tpinit($link,$file,$row);

# Messages
if ( $message !== '')
{
    echo "<h4> <span style='color:red'>$message</span> </h4>";
}

# Classification - State (provisional / final YET TO IMPLEMENT)
echo "<h5 class='classdef'> $classstr $state </h5> \n";

// Determine scoring params / details ..

$tasTotal = 0;
$query = "select count(*) from tblTask where comPk=$comPk";
$result = mysqli_query($link, $query);
if ($result)
{
    $tasTotal = mysqli_result($result, 0, 0);
}
if ($comOverall == 'all')
{
    # total # of tasks
    $comOverallParam = $tasTotal;
    $overstr = "All rounds";
}
elseif ($comOverall == 'round')
{
    $overstr = "$comOverallParam rounds";
}
elseif ($comOverall == 'round-perc')
{
    $comOverallParam = round($tasTotal * $comOverallParam / 100, 0);
    $overstr = "$comOverallParam rounds";
}
elseif ($comOverall == 'ftv')
{
    if ( strstr($comFormula, 'pwc') ) # calculates FTV parameters based on winner score (PWC)
    {
        #check if external comp
        $tbl = 'tblTaskResult TR';
        if ( $comExt == 1 )
        {
            $tbl = 'tblExtResult TR';
        }
        $sql = "SELECT
                    DISTINCT T.tasPk,
                    (
                        SELECT
                            MAX(TR.tarScore)
                        FROM
                            $tbl
                        WHERE
                            TR.tasPk = T.tasPk
                    ) AS maxScore
                FROM
                    tblTask T,
                    $tbl
                WHERE
                    T.comPk = $comPk";
        $result = mysqli_query($link, $sql) or die('Error ' . mysqli_errno($link) . ' Task validity query failed: ' . mysqli_connect_error());
        $totalvalidity = 0;
        while ( $rows = mysqli_fetch_assoc($result) )
        {
            $totalvalidity += $rows{'maxScore'};
        }
        $totalvalidity = round($totalvalidity * $comOverallParam / 100, 0); # gives total amount of available points
        $overstr = "FTV $comOverallParam% ($totalvalidity pts)";
        $comOverallParam = $totalvalidity;
    }
    else # calculates FTV parameters based on task validity (FAI)
    {
        $sql = "SELECT
                    SUM(tasQuality) as totValidity
                FROM
                    tblTask
                WHERE
                    comPk = $comPk";
        $result = mysqli_query($link, $sql) or die('Error ' . mysqli_errno($link) . ' Task validity query failed: ' . mysqli_connect_error());
        $totalvalidity = round(mysqli_result($result, 0, 0) * $comOverallParam * 10,0);
        $overstr = "FTV $comOverallParam% ($totalvalidity pts)";
        $comOverallParam = $totalvalidity;
    }
}

$today = getdate();
$tdate = sprintf("%04d-%02d-%02d", $today['year'], $today['mon'], $today['mday']);
// Fix: make this configurable
if (0 && $tdate == $comDateTo)
{
    $usePk = check_auth('system');
    $link = db_connect();
    $isadmin = is_admin('admin',$usePk,$comPk);

    if ($isadmin == 0)
    {
        echo "<h2>Results currently unavailable</h2>";
        exit(0);
    }
}

$rtable = [];
$rdec = [];

// if ($comClass == "HG")
// {
//     $classopts = array ( 'open' => '', 'floater' => '&class=0', 'kingpost' => '&class=1',
//         'hg-open' => '&class=2', 'rigid' => '&class=3', 'women' => '&class=4', 'masters' => '&class=5', 'teams' => '&class=8' );
// }
// else
// {
//     $classopts = array ( 'open' => '', 'fun' => '&class=0', 'sports' => '&class=1',
//         'serial' => '&class=2', 'women' => '&class=4', 'masters' => '&class=5', 'teams' => '&class=8', 'handicap' => '&class=9' );
// }
// $cind = '';
// if ($class != '')
// {
//     $cind = "&class=$class";
// }
// $copts = [];
// foreach ($classopts as $text => $url)
// {
//     if ($text == 'teams' && $comTeamScoring == 'aggregate')
//     {
//         # Hack for now
//         $copts[$text] = "team_comp_result.php?comPk=$comPk";
//     }
//     else
//     {
//         $copts[$text] = "comp_result.php?comPk=$comPk$url";
//     }
// }

$rdec[] = 'class="h"';
$rdec[] = 'class="h"';
if (reqival('id') == 1)
{
    $hdr = array( fb('Res'), classselector($link, $claPk, $cval), fb('Nation'), fb('Sex'), fb('FAI'), fb('CIVL'), fb('Total') );
    $hdr2 = array( '', '', '', '', '', '', '' );
}
else
{
    //$hdr = array( fb('Class.'),  fselect('class', "comp_result.php?comPk=$comPk$cind", $copts, ' onchange="document.location.href=this.value"'), '', '', '');
    if ( !$comExt )
    {
        $hdr = array( fb('Class.'),  classselector($link, $claPk, $cval), '', '', '', '');
    }
    else
    {
        $hdr = array( '',  '', '', '', '', '');
    }

    $hdr2 = array( fb('Pos.'), fb('Name'), fb('Nat.'), fb('Glider'), fb('Sponsor'), fb('Total') );
}

# find each task details
$alltasks = [];
$taskinfo = [];
$sorted = [];
if ($class == "8")
{
    if ($comTeamScoring == 'handicap')
    {
        team_handicap_result($comPk,$how,$param);
    }
}
else if ($comType == 'RACE' || $comType == 'Team-RACE' || $comType == 'Route' || $comType == 'RACE-handicap')
{
    $query = "select T.* from tblTask T where T.comPk=$comPk order by T.tasDate";
    $result = mysqli_query($link, $query) or die('Error ' . mysqli_errno($link) . ' Task query failed: ' . mysqli_connect_error());
    while ($row = mysqli_fetch_assoc($result))
    {
        $alltasks[] = isset($row['tasName']) ? $row['tasName'] : '';
        $taskinfo[] = $row;
    }

    if ($comType == 'Team-RACE')
    {
        $sorted = team_gap_result($comPk, $comOverall, $comOverallParam);
        $subtask = 'team_';
    }
    else if ($class == "9")
    {
        $sorted = overall_handicap($link, $comPk, $comOverall, $comOverallParam, $fdhv);
        $subtask = '';
    }
    else
    {
        $sorted = comp_result($link, $comPk, $comOverall, $comOverallParam, $fdhv, $tasTotal, $comExt);
        $subtask = '';
    }

    foreach ($taskinfo as $row)
    {
        $tasName = isset($row['tasName']) ? $row['tasName'] : '';
        $tasPk = $row['tasPk'];
        $tasDate = substr(isset($row['tasDate']) ? $row['tasDate'] : '',0,10);
        $hdr2[] = fb("<a href=\"${subtask}task_result.php?comPk=$comPk&tasPk=$tasPk\">$tasName</a>");
    }
    foreach ($taskinfo as $row)
    {
        $tasPk = $row['tasPk'];
        if ($row['tasTaskType'] == 'airgain')
        {
            $treg = $row['regPk'];
            $hdr[] = "<a href=\"waypoint_map.php?regPk=$treg\">Map</a>";
        }
        else
        {
            $hdr[] = "<a href=\"route_map.php?comPk=$comPk&tasPk=$tasPk\">Map</a>";
        }
    }
    $rtable[] = $hdr;
    $rtable[] = $hdr2;

    $lasttot = 0;
    $count = 1;
    foreach ($sorted as $pil => $arr)
    {
        $nxt = [];
        if ($count % 2)
        {
            $rdec[] = 'class="d"';
        }
        else
        {
            $rdec[] = 'class="l"';
        }
        $tot = 0 + $pil;
        if ($tot != $lasttot)
        {
            $nxt[] = $count;
            $nxt[] = str_replace('\' ', '\'', ucwords(str_replace('\'', '\' ', strtolower($arr['name']))));
        }
        else
        {
            $nxt[] = '';
            $nxt[] = ucfirst(strtolower($arr['name']));
        }
        if (array_key_exists('id', $_REQUEST) and ($_REQUEST['id'] == '1'))
        {
            $nxt[] = $arr['nation'];
            $nxt[] = $arr['gender'];
            $nxt[] = $arr['hgfa'];
            $nxt[] = $arr['civl'];
        }
        else
        {
            $nxt[] = $arr['nation'];
            $nxt[] = ( (stripos($arr['glider'], 'Unknown') !== false) ? '' : str_replace('\' ', '\'', ucwords(str_replace('\'', '\' ', strtolower($arr['glider'])))) );
        }
        $nxt[] = $arr['sponsor'];
        $nxt[] = fb($tot);
        $lasttot = $tot;

        foreach ($alltasks as $num => $name)
        {
            $score = 0;
            $perc = 100;
            if (array_key_exists($name, $arr))
            {
                $score = $arr[$name]['score'];
                $perc = round(isset($arr[$name]['perc']) ? $arr[$name]['perc'] : 0, 2); # I guess we need this because probably perc was not inizialized 0
            }
            if (!$score)
            {
                $score = 0;
            }
            if ($perc == 100)
            {
                $nxt[] = $score;
            }
            elseif ($perc > 0)
            {
                # $nxt[] = "$score $perc%";
                $nxt[] = round($score*$perc/100)."/<del>".$score."</del>"; # I used 5 digits for perc to have the exact sum of scores as total result. Should work but if does not, here is the problem
            }
            else
            {
                $nxt[] = "<del>$score</del>";
            }
        }
        $rtable[] = $nxt;

        $count++;
    }
    //echo ftable($rtable, "border=\"0\" cellpadding=\"2\" cellspacing=\"0\" alternate-colours=\"yes\" align=\"center\"", $rdec, '');
    echo ftable($rtable,"class='format format2 compresult'", $rdec, '');
}
else
{
    // OLC Result
    $rtable[] = array( fb('Res'),  fselect('class', "comp_result.php?comPk=$comPk$cind", $copts, ' onchange="document.location.href=this.value"'), fb('Total') );
    $rtable[] = array( '', '', '' );
    $top = 25;
    if (!$comOverallParam)
    {
        $comOverallParam = 4;
    }
    $restrict = '';
    if ($comPk == 1)
    {
        $restrict = " $fdhv";
    }
    elseif ($comPk > 1)
    {
        $restrict = " and CTT.comPk=$comPk $fdhv";
    }
    if ($class == "9")
    {
        $sorted = olc_handicap_result($link, $comOverallParam, $restrict);
    }
    else
    {
        $sorted = olc_result($link, $comOverallParam, $restrict);
    }
    $size = sizeof($sorted);

    $count = $start+1;
    $sorted = array_slice($sorted,$start,$top+2);
    $count = display_olc_result($comPk,$rtable,$sorted,$top,$count,$start);

    if ($count == 1)
    {
        echo "<b>No tracks submitted for $comName yet.</b>\n";
    }
    else
    {
        if ($start >= 0)
        {
            echo "<b class=\"left\"><a href=\"comp_result.php?comPk=$comPk&start=" . ($start-$top) . "\">Prev 25</a></b>\n";
        }
        if ($count < $size)
        {
            echo "<b class=\"right\"><a href=\"comp_result.php?comPk=$comPk&start=" . ($count) . "\">Next 25</a></b>\n";
        }
    }

}

//echo "</table>";

// FTV INFO
// if ($comOverall == 'ftv')
// {
//     echo "1. Click <a href=\"ftv.php?comPk=$comPk\">here</a> for an explanation of FTV<br>";
//     //echo "2. Scores in bold 100%, or indicated %, other scores not counted<br>";
// }

tpfooter($file);

?>
