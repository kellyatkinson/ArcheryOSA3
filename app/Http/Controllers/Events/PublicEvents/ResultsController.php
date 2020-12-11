<?php

namespace App\Http\Controllers\Events\PublicEvents;

use App\Http\Controllers\Events\PublicEvents\Event\EventResultsController;
use App\Http\Controllers\Events\PublicEvents\League\LeagueResultsController;
use App\Http\Controllers\Events\PublicEvents\Postal\PostalResultsController;
use App\Models\Division;
use App\Models\Event;
use App\Models\EventCompetition;
use App\Models\Score;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\UserResults;


class ResultsController extends EventController
{
    use UserResults;


    /**
     * MAIN entry point into get results.
     *  - Does filtering between league and events
     *
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Http\RedirectResponse|\Illuminate\View\View
     */
    public function getCompetitionResults(Request $request)
    {
        $event = Event::where('eventurl', $request->eventurl)->first();

        if (empty($event) || empty($request->eventcompetitionid) || empty($request->eventurl)) {
            return back()->with('failure', 'Invalid Request');
        }

        if (strcasecmp($request->eventcompetitionid, 'overall') === 0) {
            // league processing
            if ($event->isLeague()) {
                return (new LeagueResultsController())->getLeagueOverallResults($event);
            }

            if ($event->ispostal()) {
                return (new PostalResultsController())->getOverallResults($event);
            }

            // Normal Event
            return (new EventResultsController())->getOverallResults($event);
        }

        // league processing
        if ($event->isLeague()) {
            return (new LeagueResultsController())->getLeagueCompetitionResults($event, $request->eventcompetitionid);
        }

        // Get the results for the event and the eventcompetitionid
        return (new EventResultsController())->getEventCompetitionResults($event, $request->eventcompetitionid);

    }



    /**
     * Get the Events competitions and their results status
     *
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory| \Illuminate\Http\RedirectResponse| \Illuminate\Routing\Redirector| \Illuminate\View\View
     */
    public function getEventResultsList(Request $request)
    {
        $event = Event::where('eventurl', $request->eventurl)->first();

        if (empty($event)) {
            return redirect('/');
        }

        $overall = $event->showoverall;

        // league event
        if ($event->isLeague()) {
            $eventcompetition = EventCompetition::where('eventid', $event->eventid)->first();

            $rangeArr = [];
            foreach (range(1, $eventcompetition->currentweek) as $week) {
                $score = Score::where('eventid', $eventcompetition->eventid)
                    ->where('eventcompetitionid', $eventcompetition->eventcompetitionid)
                    ->where('week', $week)
                    ->first();

                if (!empty($score)) {
                    $rangeArr[] = $week;
                }
            }

            return view('events.results.league.leaguecompetitions', compact('event', 'rangeArr', 'overall'));
        }


        // not a league
        $eventcompetitions = EventCompetition::where('eventid', $event->eventid)->orderBy('date', 'asc')->get();
        $haveScores = false;
        foreach ($eventcompetitions as $eventcompetition) {

            $eventcompetition->score = Score::where('eventid', $eventcompetition->eventid)
                                            ->where('eventcompetitionid', $eventcompetition->eventcompetitionid)
                                            ->first();
            if (empty($haveScores) && !empty($eventcompetition->score)) {
                $haveScores = true;
            }

        }

        // dont show overall if there are no results
        if ($overall && !$haveScores) {
            $overall = false;
        }
        return view('events.results.eventcompetitions', compact('event', 'eventcompetitions', 'overall'));
    }


    /**
     * Returns the event's entrys sorted
     * @param $eventid
     * @return array|bool|mixed
     */
    public function getEventEntrySorted($eventid, $userid = null, $groupbyentry = false)
    {
        $and = '';
        $args = ['eventid' => $eventid];
        if (!empty($userid)) {
            $and = ' AND `ee`.`userid` = :userid ';
            $args['userid'] = $userid;
        }

        $groupby = '';
        if (!empty($groupbyentry)) {
            $groupby = " GROUP BY `ee`.`entryid` ";
        }

        $entrys = DB::select("
            SELECT ee.userid, ee.firstname, ee.lastname, ee.gender, ec.roundid, ec.divisionid,  
                  d.label as divisionname, d.bowtype, r.unit, r.code, r.label as roundname, s.label as schoolname, u.username
            FROM `evententrys` ee
            JOIN `users` u ON (ee.userid = u.userid)
            JOIN `entrycompetitions` ec ON (ec.`entryid` = ee.`entryid`)
            JOIN `divisions` d ON (`ec`.`divisionid` = `d`.`divisionid`)
            JOIN `rounds` r ON (ec.roundid = r.roundid)
            JOIN `scores_flat` sf ON (ee.entryid = sf.entryid AND `sf`.`divisionid` = ec.divisionid)
            LEFT JOIN `schools` s ON (ee.schoolid = s.schoolid)
            WHERE `ee`.`eventid` = :eventid
            AND `ee`.`entrystatusid` = 2
            $and 
            $groupby
            ORDER BY d.label, ee.userid, ec.eventcompetitionid
        ", $args);

        // Get all the divisions
        static $alldivisions;
        if (empty($alldivisions)) {
            $alldivisions = Division::all()->keyBy('divisionid')->toArray();
        }


        $sortedEntrys = [];

        foreach ($entrys as $entry) {
            if (strpos($entry->divisionid, ',') !== false) {
                $divisionids = explode(',', $entry->divisionid);

                foreach ($divisionids as $divisionid) {
                    // clone the entry
                    $entryUpdated = clone $entry;
                    $divison = (object) $alldivisions[$divisionid];

                    $entryUpdated->bowtype = $divison->bowtype;
                    $entryUpdated->divisionname = $divison->label;
                    $entryUpdated->divisionid = $divisionid;
                    $sortedEntrys[] = $entryUpdated;
                }
            }
            else {
                $sortedEntrys[] = $entry;
            }
        }

        return $sortedEntrys;

    }
}
