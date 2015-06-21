#!/usr/bin/perl -I/home/geoff/bin


#
# Determines how much of a task (and time) is completed
# given a particular competition / task 
# 
# Geoff Wong 2007
#

package GGap;

require Gap;
@ISA = ( "Gap" );

#require DBD::mysql;
use POSIX qw(ceil floor);
use Data::Dumper;
#use Math::Trig;
#use TrackLib qw(:all);

#
# Find the task totals and update ..
#   tasTotalDistanceFlown, tasPilotsLaunched, tasPilotsTotal
#   tasPilotsGoal, tasPilotsLaunched, 
#
# FIX: TotalDistance wrong with 'lo' type results?
#
sub task_totals
{
    my ($self, $dbh, $task, $formula) = @_;
    my $taskt;
    my $tasPk;
    my ($minarr, $fastest, $firstdep, $mincoeff);
    my $mindist;
    my $kmmarker;

    $kmmarker = [];
    $tasPk = $task->{'tasPk'};

    $taskt = Gap::task_totals(@_);

    # Determine first to reach each 'KM' marker
    $sth = $dbh->prepare("select M.tmDistance, min(M.tmTime) as FirstArrival from 
            tblTrackMarker M, tblComTaskTrack C where C.tasPk=$tasPk and M.traPk=C.traPk and M.tmTime > 0 group by M.tmDistance order by M.tmDistance");
    $sth->execute();
    while ($ref = $sth->fetchrow_hashref())
    {
        $kmmarker->[$ref->{'tmDistance'}] = $ref->{'FirstArrival'};
    }

    $taskt->{'kmmarker'} = $kmmarker;

    print Dumper($taskt);
    return $taskt;
}

sub points_weight
{
    my ($self, $task, $taskt, $formula) = @_;
    my $Fversion;
    my $quality;
    my $distweight;
    my $Adistance;
    my $Aspeed;
    my $Astart;
    my $Aarrival;
    my $x;

    $quality = $taskt->{'quality'};

    #print "distweight=$distweight($Ngoal/$Nfly)\n";
    $Astart = 250 * $quality;
    $Aarrival = 0; 

    $x = $taskt->{'goal'} / $taskt->{'launched'};
    $distweight = 1-0.8*sqrt($x);
    $Fversion = $formula->{'version'};
    
    # need to rescale if depart / arrival are "off"
    if ($task->{'departure'} eq 'off')
    {
        $Astart = 0;
        if ($task->{'arrival'} eq 'on')
        {
            $Adistance = 1000 * $quality * $distweight;
            $Aspeed = 1000 * $quality * (1-$distweight) * 3/4;
            $Aarrival = 1000 * $quality * (1-$distweight) * 1/4;
        }
        else
        {
            $Aarrival = 0; 
            $Adistance = 1000 * $quality * $distweight;
            $Aspeed = 1000 * $quality * (1-$distweight);
        }
    }
    else
    {
        $Astart = 250 * $quality;
        if ($task->{'arrival'} eq 'on')
        {
            $Adistance = 750 * $quality * $distweight;
            $Aspeed = 750 * $quality * (1-$distweight) * 3/4;
            $Aarrival = 750 * $quality * (1-$distweight) * 1/4;
        }
        else
        {
            $Aarrival = 0; 
            $Adistance = 750 * $quality * $distweight;
            $Aspeed = 750 * $quality * (1-$distweight);
        }
    }


    print "points_weight: (GGap) Adist=$Adistance, Aspeed=$Aspeed, Astart=$Astart, Aarrival=$Aarrival\n";

    return ($Adistance, $Aspeed, $Astart, $Aarrival);
}


sub points_allocation
{
    my ($self, $dbh, $task, $taskt, $formula) = @_;
    my $tasPk;
    my $quality;
    my ($Ngoal,$Nfly);
    my ($Tnom, $Tmin);
    my $Tmindist;
    my $Tfarr;
    my $Fclass;
    my $Fversion;
    my $Cmin;

    my $x;
    my $distweight;

    my $Adistance;
    my $Aspeed;
    my $Astart;
    my $Arival;

    my $penspeed;
    my $pendist;
    my $difdist;

    my @pilots;
    my @tmarker;
    my $kmdiff = [];
    my ($sub, $sref);
    my $hbess;

    # Find fastest pilot into goal and calculate leading coefficients
    # for each track .. (GAP2002 only?)

    $tasPk = $task->{'tasPk'};
    $quality = $taskt->{'quality'};
    $Ngoal = $taskt->{'goal'};
    $Nfly = $taskt->{'launched'};
    $Tmin = $taskt->{'fastest'};
    $Tfarr = $taskt->{'firstarrival'};
    $Cmin = $taskt->{'mincoeff'};

    $Tnom = $formula->{'nomtime'} * 60;
    $Tmindist = $formula->{'mindist'};
    $Fclass = $formula->{'class'};
    $Fversion = $formula->{'version'};

    print "Tnom=$Tnom Tmindist=$Tmindist Flcass=$Fclass Fversion=$Fversion Nfly=$Nfly\n";

    # Some GAP basics
    ($Adistance, $Aspeed, $Astart, $Aarrival) = $self->points_weight($task, $taskt, $formula);

    $kmdiff = $self->calc_kmdiff($dbh, $task, $taskt, $formula);
    print Dumper($kmdiff);

    # Get all pilots and process each of them 
    # pity it can't be done as a single update ...
    $dbh->do('set @x=0;');
    $sth = $dbh->prepare("select \@x:=\@x+1 as Place, tarPk, traPk, tarDistance, tarSS, tarES, tarPenalty, tarResultType, tarLeadingCoeff, tarGoal, tarLastAltitude from tblTaskResult where tasPk=$tasPk and tarResultType <> 'abs' order by tarDistance desc, tarES");
    $sth->execute();
    while ($ref = $sth->fetchrow_hashref()) 
    {
        my %taskres;

        %taskres = ();
        $taskres{'tarPk'} = $ref->{'tarPk'};
        $taskres{'traPk'} = $ref->{'traPk'};
        $taskres{'penalty'} = $ref->{'tarPenalty'};
        $taskres{'distance'} = $ref->{'tarDistance'};
        # set pilot to min distance if they're below that ..
        if ($taskres{'distance'} < $formula->{'mindist'})
        {
            $taskres{'distance'} = $formula->{'mindist'};
        }
        $taskres{'result'} = $ref->{'tarResultType'};
        $taskres{'startSS'} = $ref->{'tarSS'};
        $taskres{'endSS'} = $ref->{'tarES'};
        # OZGAP2005 
        $taskres{'timeafter'} = $ref->{'tarES'} - $Tfarr;
        $taskres{'place'} = $ref->{'Place'};
        $taskres{'time'} = $taskres{'endSS'} - $taskres{'startSS'};
        $taskres{'goal'} = $ref->{'tarGoal'};
        $taskres{'lastalt'} = $ref->{'tarLastAltitude'};
        # Determine ESS time bonus against goal height
        $hbess = 0;
        if ($taskres{'goal'} > 0)
        {
            my $habove = $taskres{'lastalt'} - $taskt->{'goalalt'};
            print "habove: $habove (", $taskt->{'goalalt'}, ")\n";
            if ($habove > 400)
            {
                $habove = 400;
            }
            if ($habove > 50)
            {
                print "oldtime=", $taskres{'time'};
                $hbess = 20.0*(($habove-50.0)**0.40);
                $taskres{'time'} = $taskres{'time'} - $hbess;
                print " hbess=$hbess time=", $taskres{'time'}, "\n";
                if ($taskres{'time'} < $Tmin)
                {
                    $Tmin = $taskres{'time'};
                }
            }
        }
        $taskres{'hbess'} = $hbess;

        if ($taskres{'time'} < 0)
        {
            $taskres{'time'} = 0;
        }
        # Leadout Points
        $taskres{'coeff'} = $ref->{'tarLeadingCoeff'};
        $taskres{'kmmarker'} = [];
        # FIX: adjust against fastest ..
        $kt = $taskres{'kmmarker'};
        $sub = $dbh->prepare("select * from tblTrackMarker where traPk=" . $taskres{'traPk'} . " order by tmDistance");
        $sub->execute();
        while ($sref = $sub->fetchrow_hashref()) 
        {
            #print "sref=",, $sref->{'tmTime'}, "\n";
            push @$kt, $sref->{'tmTime'};
        }

        push @pilots, \%taskres;
    }

    # Score each pilot now 
    my $kmarr;
    $kmarr = $taskt->{'kmmarker'};
    @tmarker = @$kmarr;
    for my $pil ( @pilots )
    {
        my $Pdist;
        my $Pspeed;
        my $Parrival;
        my $Pdepart;
        my $Pscore;
        my $tarPk;
        my $penalty;

        $tarPk = $pil->{'tarPk'};
        $penalty = $pil->{'penalty'};

        # Pilot distance score 
        #print "task->maxdist=", $taskt->{'maxdist'}, "\n";
        #print "pil->distance/(2*maxdist)=", $pil->{'distance'}/(2*$taskt->{'maxdist'}), "\n";
        #print "kmdiff=", $kmdiff[floor($pil->{'distance'}/1000.0)], "\n";

        $Pdist = $Adistance * (($pil->{'distance'}/$taskt->{'maxdist'}) * $formula->{'lineardist'}
                + $kmdiff->[floor($pil->{'distance'}/100.0)] * (1-$formula->{'lineardist'}));

        # Pilot speed score
        print "$tarPk speed: ", $pil->{'time'}, ", $Tmin\n";
        if ($pil->{'time'} > 0)
        {
            $Pspeed = $Aspeed * (1-(($pil->{'time'}-$Tmin)/3600/sqrt($Tmin/3600))**(2/3));
        }
        else
        {
            $Pspeed = 0;
        }

        if ($Pspeed < 0)
        {
            $Pspeed = 0;
        }

        if (0+$Pspeed != $Pspeed)
        {
            print "Pdepart is nan for $tarPk, pil->{'time'}=", $pil->{'time'}, "\n";
            $Pspeed = 0;
        }

        # Pilot departure score
        print "$tarPk pil->startSS=", $pil->{'startSS'}, "\n";
        print "$tarPk pil->endSS=", $pil->{'endSS'}, "\n";
        print "$tarPk tast->first=", $taskt->{'firstdepart'}, "\n";

        $Pdepart = 0;

        # KmBonus award points
        if (scalar(@tmarker) > 0)
        {
            for my $km (1..scalar(@tmarker))
            {
                #print Dumper($pil->{'kmmarker'});
                if ($pil->{'kmmarker'}->[$km] > 0 && $tmarker[$km] > 0)
                {
                    #$x = 1 - ($pil->{'kmmarker'}->[$km] - $tmarker[$km]) / ($tmarker[$km]/4);
                    $x = 1 - ($pil->{'kmmarker'}->[$km] - $tmarker[$km]) / 600;
                    if ($x > 0)
                    {
                        $Pdepart = $Pdepart + (0.2+0.037*$x+0.13*($x*$x)+0.633*($x*$x*$x));
                    }
                }
            }
            $Pdepart = $Pdepart * $Astart / floor($task->{'ssdistance'}/1000.0);
        }
        else
        {
            $Pdepart = 0;
        }

        # Sanity
        if ($Pdepart < 0)
        {
            $Pdepart = 0;
        }

        # Pilot arrival score
        $Parrival = 0;
        if ($pil->{'time'} > 0)
        {
            # OzGAP / Timed arrival
            print "$tarPk time arrival ", $pil->{'timeafter'}, ", $Ngoal\n";
            $x = 1-$pil->{'timeafter'}/(90*60);

            $Parrival = $Aarrival*(0.2+0.037*$x+0.13*($x*$x)+0.633*($x*$x*$x));
            print "x=$x parrive=$Parrival\n";
        }
        if ($Parrival < 0)
        {
            $Parrival = 0;
        }

        # Penalty for not making goal ..
        if ($pil->{'goal'} == 0)
        {
            # Oz comps ...
            $Pspeed = 0;
            $Parrival = 0;
        }

        if (($pil->{'result'} eq 'dnf') or ($pil->{'result'} eq 'abs'))
        {
            $Pdist = 0;
            $Pspeed = 0;
            $Parrival = 0;
            $Pdepart = 0;
        }

        if (0+$Pdepart != $Pdepart)
        {
            print "Pdepart is nan for $tarPk\n";
            $Pdepart = 0;
        }

        # Penalty is in seconds .. convert for OzGap penalty.
#        if ($penalty > 0) 
#        {
#            $penspeed = $penalty;
#            if ($penspeed > 90)
#            {
#                $penspeed = 90;
#            };
#            $penspeed = ($penspeed + 10) / 100;
#            $penspeed = ($Pdepart + $Pspeed) * $penspeed;
#
#            $pendist = 0;
#            $penalty = $penalty - 90;
#            if ($penalty > 0)
#            {
#                if ($penalty > $Tnom / 3)
#                {
#                    $pendist = $Pdist;
#                }
#                else
#                {
#                    $pendist = ((($penalty + 30) / 60) * 2) / 100;
#                    $pendist = $Pdist * $penalty;
#                }
#            }
#
#            print "jumped=$penalty penspeed=$penspeed pendist=$pendist\n";
#
#            $penalty = int($penspeed + $pendist + 0.5);
#            print "computed penalty=$penalty\n";
#        }

        $Pscore = $Pdist + $Pspeed + $Parrival + $Pdepart - $penalty;

        # Store back into tblTaskResult ...
        if (defined($tarPk))
        {
            print "update $tarPk: dst:$Pdist, spd:$Pspeed, pen:$penalty, arr:$Parrival, dep:$Pdepart\n";
            $sth = $dbh->prepare("update tblTaskResult set
                tarDistanceScore=$Pdist, tarSpeedScore=$Pspeed, 
                tarArrival=$Parrival, tarDeparture=$Pdepart, tarScore=$Pscore
                where tarPk=$tarPk");
            $sth->execute();
        }
    }
}

1;
