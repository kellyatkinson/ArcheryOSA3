<?php

namespace App\Http\Controllers\Events\Auth;

use App\Http\Requests\Auth\EventRegistration\CreateRegistration;
use App\Http\Requests\Auth\EventRegistration\UpdateRegistration;
use App\Jobs\SendArcherContactAdminEmail;
use App\Jobs\SendArcherRelationConfirm;
use App\Jobs\SendEntryReceived;
use App\Jobs\SendEventAdminEntryReceived;
use App\Models\Cart;
use App\Models\Club;
use App\Models\Division;
use App\Models\EntryCompetition;
use App\Models\Event;
use App\Models\EventCompetition;
use App\Models\EventEntry;
use App\Models\Round;
use App\Models\School;
use App\Models\UserRelation;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Webpatser\Countries\Countries;


class EventRegistrationController extends EventController
{

    public function sendContactToAdmin(Request $request)
    {
        $evententry = EventEntry::where('entryid', $request->entryid)->first();

        $event = Event::where('eventid', $evententry->eventid)->first();

        if (empty($evententry) || empty($request->message) || empty($event)) {
            return json_encode(false);
        }

        $from = ($evententry->firstname ?? '') . ' ' . ($evententry->lastname ?? '');

        $user = User::where('userid', $evententry->userid)->first();

        $entryurl = route('evententryupdate', ['eventurl' => $event->eventurl, 'username' => $user->username]);

        if (empty($user) || empty($entryurl)) {
            return json_encode(false);
        }

        SendArcherContactAdminEmail::dispatch($event, $entryurl, $from, $request->message);

        $evententry->contactmessage = $request->message;
        $evententry->save();

        return json_encode(true);

    }

    public function getRegistrationList(Request $request)
    {
        // Get the event
        $event = Event::where('eventurl', $request->eventurl)->first();


        if (empty($event)) {
            return redirect('/');
        }

        if ($event->isEvent() && !$event->canEnterEvent()) {
            return redirect('/event/details/' . $event->eventurl)->with('failure', 'Event entrys are closed');
        }

        // Try get an existing entry | redirect if exists
        $evententry = EventEntry::where('eventid', $event->eventid)->first();

        $relations = UserRelation::where('userid', Auth::id())->where('authorised', 1)->pluck('relationid')->toarray();

        if (!empty($relations)) {
            $relations = User::wherein('userid', $relations)->get();
        }

        // Can they register for an event
        return view('events.public.registration.registrationlist',
                    compact('event', 'evententry', 'relations')
        );
    }

    public function getRegistration(Request $request)
    {
        $event = Event::where('eventurl', $request->eventurl ?? -1)->first();

        $user  = User::where('username', $request->username ?? -1)->first();

        if (empty($event) || empty($user)) {
            return back();
        }

        $evententry = EventEntry::where('eventid', $event->eventid)
                                ->where('userid', $user->userid)
                                ->first();

        // means they have an entry already, return that
        if (!empty($evententry)) {
            return $this->getRegistrationUpdate($event, $user, $evententry);
        }

        if ($event->isEvent() && !$event->canEnterEvent()) {
            return redirect('/event/details/' . $event->eventurl)->with('failure', 'Event entrys are closed');
        }

        $canusecc = $event->canUseCC();

        $countrys = Countries::all();


        if ($event->isNonShooting()) {
            return view('events.public.registration.createregistration-nonscoring',
                compact('user', 'event', 'countrys', 'canusecc'));
        }

        $eventcompetitions = DB::select("
            SELECT *
            FROM `eventcompetitions`
            WHERE `eventid` = :eventid
            ORDER BY `date` ASC
        ", ['eventid' => $event->eventid]);


        if (empty($eventcompetitions)) {
            return back()->with('failure', 'Unable to get entry form');
        }

        $leaguecompround = null;
        if ($event->isLeague()) {
            $leaguecompround = reset($eventcompetitions);
            $leaguecompround = $leaguecompround->eventcompetitionid;
        }

        $divisionsfinal    = [];
        $competitionsfinal = [];
        foreach ($eventcompetitions as $eventcompetition) {

            $eventcompetition->divisioncomplete = $divisions = Division::wherein('divisionid', json_decode($eventcompetition->divisionids))->orderBy('bowtype')->get();

            foreach ($divisions as $division) {
                $divisionsfinal[$division->divisionid] = $division;
            }

            if ($event->isLeague()) {
                $eventcompetition->rounds = Round::where('roundid', $eventcompetition->roundids)->get();
            }
            else {
                $eventcompetition->rounds = Round::wherein('roundid', json_decode($eventcompetition->roundids))->orderby('label', 'DESC')->get();
            }

            $competitionsfinal[$eventcompetition->date][$eventcompetition->label] = $eventcompetition;

            $eventcomps[] = $eventcompetition;
        }


        $clubs = Club::where('visible', 1)->orderby('label')->get();

        $schools = null;
        if ($event->schoolrequired) {
            $schools = School::where('visible', 1)->orderby('label')->get();
        }


        // Means they need to create an event
        if (empty($evententry)) {
            return view('events.public.registration.createregistration',
                    compact('eventcomps', 'user', 'event', 'clubs', 'divisionsfinal', 'competitionsfinal',
                        'leaguecompround', 'schools','countrys', 'canusecc'));
        }

    }

    public function getRegistrationUpdate($event, $user, $evententry)
    {
        if (empty($event) || empty($user)) {
            return back()->with('failure', 'Cannot process');
        }


        $countrys = Countries::all();

        $canusecc = $event->canUseCC();


        if ($event->isNonShooting()) {
            return view('events.public.registration.updateregistration-nonshooting',
                compact('user', 'event', 'countrys', 'evententry', 'canusecc'));
        }


        $eventcompetitions = DB::select("
            SELECT *
            FROM `eventcompetitions`
            WHERE `eventid` = :eventid
            ORDER BY `date` ASC
        ", ['eventid' => $event->eventid]);


        if (empty($eventcompetitions)) {
            return back()->with('failure', 'Unable to get entry form');
        }


        $divisionsfinal = [];
        foreach ($eventcompetitions as $eventcompetition) {
            $eventcompetition->divisioncomplete = $divisions = Division::wherein('divisionid', json_decode($eventcompetition->divisionids))->orderBy('bowtype')->get();

            foreach ($divisions as $division) {
                $divisionsfinal[$division->divisionid] = $division;
            }

            if ($event->isLeague()) {
                $eventcompetition->rounds = Round::where('roundid', $eventcompetition->roundids)->get();
            }
            else {
                $eventcompetition->rounds = Round::wherein('roundid', json_decode($eventcompetition->roundids))->get();
            }

            $eventcomps[] = $eventcompetition;
        }


        // Get an array of the users entry divisions
        $userentrydivisions = [];
        $userentryrounds = [];
        foreach ($evententry->entrycompetitions() as $entrycomp) {

            if ($event->isleague()) {
                $userentrydivisions[] = $entrycomp->divisionid;
                continue;
            }
            $userentrydivisions[$entrycomp->eventcompetitionid] = $entrycomp->divisionid;
            $userentryrounds[$entrycomp->eventcompetitionid] = $entrycomp->roundid;
        }

        $clubs = Club::where('visible', 1)->orderby('label')->get();

        $schools = null;
        if ($event->schoolrequired) {
            $schools = School::where('visible', 1)->orderby('label')->get();
        }


        return view('events.public.registration.updateregistration',
            compact('userentrydivisions','userentryrounds','eventcomps',
                'evententry', 'user', 'event', 'clubs', 'divisionsfinal',
                'schools','countrys','canusecc'));

    }


    public function getEventCompetitionList(Request $request)
    {

        $event = Event::where('eventurl', $request->eventurl ?? -1)->first();

        $user  = User::where('username', $request->username ?? -1)->first();

        if (empty($event) || empty($user)) {
            return back();
        }

        if ($event->isEvent() && !$event->canEnterEvent()) {
            return redirect('/event/details/' . $event->eventurl)->with('failure', 'Event entrys are closed');
        }

        $eventcompetitions = DB::select("
            SELECT ec.*, ee.entrystatusid, u.username, IFNULL(es.label, 'Not Entered') as status
            FROM `eventcompetitions` ec
            LEFT JOIN `entrycompetitions` enc ON (ec.eventcompetitionid = enc.eventcompetitionid AND enc.userid = :userid)
            LEFT JOIN `evententrys` ee ON (ee.eventid = ec.eventid AND ee.userid = :userid1 )
            LEFT JOIN `users` u ON (u.userid = :userid2)
            LEFT JOIN `entrystatus` es ON (ee.entrystatusid = es.entrystatusid)
            WHERE ec.`eventid` = :eventid
            ORDER BY ec.`date` ASC
        ", ['eventid' => $event->eventid, 'userid' => $user->userid, 'userid1' => $user->userid, 'userid2' => $user->userid]);

        return view('events.public.registration.eventcompetitionlist',
            compact('user', 'event', 'eventcompetitions'));

    }

    protected function getRequestDetails($validated, $required)
    {

        $return = [];
        foreach ($required as $r) {
            if (isset($validated[$r])) {
                $return[$r] = $validated[$r];
            }
        }

        return json_encode($return);
    }

    protected function create($event, $evententry, $eventcompetition, $validated)
    {
        $entrycomps = [];
        if ($event->isleague()) {
            foreach ($validated['divisionid'] as $divisionid) {

                $entrycompetition = new EntryCompetition();
                $entrycompetition->entryid            = $evententry->entryid;
                $entrycompetition->eventid            = $event->eventid;
                $entrycompetition->userid             = $validated['userid'];
                $entrycompetition->competitionid      = '';
                $entrycompetition->eventcompetitionid = $eventcompetition->eventcompetitionid;
                $entrycompetition->divisionid         = $divisionid;
                $entrycompetition->competitionid      = '';
                $entrycompetition->roundid            = $eventcompetition->roundids;
                $entrycompetition->cost               = $eventcompetition->cost;
                $entrycompetition->save();

                $entrycomps[$entrycompetition->eventcompetitionid] = $entrycompetition;
            }
        }
        else {
            foreach ($validated['roundids'] as $eventcompetitionid => $roundid) {
                $divisionid = isset($validated['divisionid'][$eventcompetitionid]) ? $validated['divisionid'][$eventcompetitionid] : null;

                // Only allow valid rounds and divisions
                if (empty((int) $roundid) || empty((int) $divisionid)) {
                    continue;
                }


                $ec = EventCompetition::where('eventcompetitionid', $eventcompetitionid)->first();

                if (empty($ec)) {
                    continue;
                }

                $entrycompetition = new EntryCompetition();
                $entrycompetition->entryid            = $evententry->entryid;
                $entrycompetition->eventid            = $event->eventid;
                $entrycompetition->userid             = $validated['userid'];
                $entrycompetition->competitionid      = '';
                $entrycompetition->eventcompetitionid = $eventcompetitionid;
                $entrycompetition->divisionid         = $divisionid;
                $entrycompetition->roundid            = $roundid;
                $entrycompetition->cost               = $ec->cost;
                $entrycompetition->save();

                $entrycomps[$entrycompetition->eventcompetitionid] = $entrycompetition;

            }
        }

        return $entrycomps;
    }


    /**
     * METHOD: POST
     * @param CreateRegistration $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function createRegistration(CreateRegistration $request)
    {
        $event = Event::where('eventurl', $request->eventurl)->first();

        if ($event->isEvent() && !$event->canEnterEvent()) {
            return redirect('/event/details/' . $event->eventurl)->with('failure', 'Event entrys are closed');
        }

        if ($event->isNonShooting()) {
            return $this->createNonShootingRegistration($event, $request);
        }

        $validated = $request->validated();

        $eventcompetition = null;
        if ($event->isLeague()) {
            // get the event competition
            $eventcompetition = EventCompetition::where('eventcompetitionid', $validated['eventcompetitionid'])->first();

            if (empty($eventcompetition)) {
                return redirect('/event/details/' . $event->eventurl)->with('failure', 'Unable to enter event');
            }
        }

        $user = Auth::user();

        if ($validated['userid'] != $user->userid) {
            // make sure the person logged in can enter the person
            $user = UserRelation::where('userid', Auth::id())
                                ->where('relationid', $validated['userid'])
                                ->where('authorised', 1)
                                ->first();

            if (!empty($user)) {
                $user = User::where('userid', $validated['userid'])->first();
            }
        }

        if (empty($event) || empty($user)) {
            return back()->with('failure', 'Please try again later');
        }

        // check to see if an entry exists for this user
        $existingevententry = EventEntry::where('eventid', $event->eventid)
                                        ->where('userid', $user->userid)
                                        ->first();

        if (!empty($existingevententry) ) {
            return back()->with('failure', 'An entry already exists, please check back in a few minutes');
        }

        if (!empty($event->waver) && empty($validated['waver'])) {
            return back()->with('failure', 'You must accept the waver to enter this competition');
        }

        $roundsexist = array_filter(array_values($validated['roundids']));

        if (($event->isEvent() || $event->isPostal()) && empty($roundsexist)) {
            return back()->with('failure', 'Please check the competitions and try again');
        }

        // This handles the case where the first event competition is not an entry
        $divisionid = 0;
        if (is_array($validated['divisionid'])) {
            foreach ($validated['divisionid'] as $did) {
                if (is_numeric($did)) {
                    $divisionid = $did;
                    break;
                }
            }
        }

        $paymenttype = $validated['paymenttype'];

        // Store the single event entry
        $evententry = new EventEntry();
        $evententry->userid        = $validated['userid'];
        $evententry->eventid       = $event->eventid;
        $evententry->entrystatusid = 1; // 1 is pending
        $evententry->waveraccepted = 1; // 1 is accepted
        $evententry->paid          = 0; // 0 is not paid yet
        $evententry->firstname     = !empty($validated['firstname'])      ? ($validated['firstname'])        : '';
        $evententry->lastname      = !empty($validated['lastname'])       ? ($validated['lastname'])         : '';
        $evententry->email         = !empty($validated['email'])          ? $validated['email']              : '';
        $evententry->address       = !empty($validated['address'])        ? ($validated['address'])          : '';
        $evententry->phone         = !empty($validated['phone'])          ? ($validated['phone'])            : '';
        $evententry->membership    = !empty($validated['membership'])     ? ($validated['membership'])       : '';
        $evententry->notes         = !empty($validated['notes'])          ? ($validated['notes'])            : '';
        $evententry->clubid        = !empty($validated['clubid'])         ? intval($validated['clubid'])     : '';
        $evententry->schoolid      = !empty($validated['schoolid'])       ? intval($validated['schoolid'])   : '';
        $evententry->country       = !empty($validated['country'])        ? ($validated['country'])          : '';
        $evententry->divisionid    = $divisionid;
        $evententry->pickup        = !empty($validated['pickup']);
        $evententry->dateofbirth   = !empty($validated['dateofbirth'])    ? $validated['dateofbirth']        : '';
        $evententry->gender        = !empty(($validated['gender'] ?? '') == 'm')  ? 'm' : 'f';
        $evententry->paymenttype   = ( !empty(($paymenttype) && in_array($paymenttype, ['bt','cc','other'])) ) ? $paymenttype : 'other';
        $evententry->details       = $this->getRequestDetails($validated, ['mqs']);
        $evententry->enteredby     = Auth::id();
        $evententry->hash          = $this->createHash();
        $evententry->save();

        $entrycomps = $this->create($event, $evententry, $eventcompetition, $validated);

        // Only add to cart if its for a CreditCard
        if ($evententry->paymenttype == 'cc') {
            Auth::user()->addentrycartitem($event, $evententry, $entrycomps);
        }

        SendEntryReceived::dispatch($evententry->email, $event->label);

        if ($event->adminnotifications) {
            SendEventAdminEntryReceived::dispatch($event->email,
                                                    $event->label,
                                                    $validated['email'],
                                                    $evententry->firstname . ' ' . $evententry->lastname,
                                                    $event->eventurl
                                        );
        }

        return redirect('/event/register/' . $event->eventurl)->with('success', 'Entry Received!');
    }


    /**
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function updateRegistration(Request $request)
    {

        $event = Event::where('eventurl', $request->eventurl)->first();

        if ($event->isEvent() && !$event->canEnterEvent()) {
            return redirect('/event/details/' . $event->eventurl)->with('failure', 'Event entrys are closed');
        }

        $user = Auth::user();

        if ($request->input('userid') != $user->userid) {
            // make sure the person logged in can enter the person
            $user = UserRelation::where('userid', Auth::id())
                ->where('relationid', $request->input('userid'))
                ->where('authorised', 1)
                ->first();

            if (!empty($user)) {
                $user = User::where('userid', $request->input('userid'))->first();
            }
        }

        if (empty($event) || empty($user)) {
            return back()->with('failure', 'Please try again later');
        }

        $paymenttype = $request->input('paymenttype');


        // Store the single event entry
        $evententry = EventEntry::where('userid', $user->userid)
                                ->where('eventid', $event->eventid)
                                ->first();

        // Only allow changing to be done by non-paid entries
        if (empty($evententry->paid) && $evententry->paymenttype != $paymenttype) {

            // If the payment type WAS CC, REMOVE the cart item if it exists still
            if ($evententry->paymenttype == 'cc') {
                Auth::user()->removeEntryFromCart($evententry->entryid);
            }
            // Entry is NOW as CC, Add to the cart
            else if ($paymenttype == 'cc') {

                $entrycomps = [];
                $entrycompetitions = EntryCompetition::where('entryid', $evententry->entryid)->get();

                foreach ($entrycompetitions as $entrycompetition) {
                    $entrycomps[$entrycompetition->eventcompetitionid] = $entrycompetition;
                }

                Auth::user()->addentrycartitem($event, $evententry, $entrycomps);
            }
        }


        $evententry->firstname    = $request->input('firstname') ?? $evententry->firstname;
        $evententry->lastname     = $request->input('lastname') ?? $evententry->lastname;
        $evententry->address      = $request->input('address') ?? $evententry->address;
        $evententry->phone        = $request->input('phone') ?? $evententry->phone;
        $evententry->membership   = $request->input('membership') ?? $evententry->membership;
        $evententry->notes        = $request->input('notes') ?? $evententry->notes;
        $evententry->clubid       = $request->input('clubid') ?? $evententry->clubid;
        $evententry->pickup       = $request->input('pickup') ? 1 : 0;
        $evententry->schoolid     = $request->input('schoolid') ?? $evententry->schoolid;
        $evententry->dateofbirth  = $request->input('dateofbirth') ?? $evententry->dateofbirth;
        $evententry->paymenttype  = ( !empty(($paymenttype) && in_array($paymenttype, ['bt','cc','other'])) ) ? $paymenttype : 'other';
        $evententry->save();




        return redirect('/event/register/' . $event->eventurl)->with('success', 'Entry Updated!');
    }



    /**
     * ADMIN UPDATE METHODS
    */
    public function createAdminRegistration(CreateRegistration $request)
    {

        $validated = $request->validated();

        $event = Event::where('eventid', $validated['eventid'])->first();

        $user = User::where('userid', $validated['userid'] ?? -1)->first();

        if (empty($event)) {
            return back()->with('failure', 'Please try again later');
        }

        // make sure they are not already entered
        $entryCheck = EventEntry::where('eventid', $event->eventid)->where('userid', $validated['userid'])->first();
        if (!empty($entryCheck)) {
            return back()->with('failure', 'Archer already entered');
        }


        $eventcompetition = null;
        if ($event->isLeague()) {
            // get the event competition
            $eventcompetition = EventCompetition::where('eventcompetitionid', $validated['eventcompetitionid'])->first();

            if (empty($eventcompetition)) {
                return back()->with('failure', 'Please contact AOSA');
            }
        }



        // could be a manual entry, try lookup by email
        if (empty($user)) {
            $user = User::where('email', $validated['email'] ?? -1)->first();

            // if still empty, create a new user
            if (empty($user)) {
                $user = new User();
                $user->firstname = ($validated['firstname']);
                $user->lastname  = ($validated['lastname']);
                $user->email     = !empty($validated['email']) ? $validated['email'] : $this->createHash(12);
                $user->roleid    = 4;
                $user->username  = (preg_replace("/[^a-zA-Z0-9]/", "", $validated['firstname'].$validated['lastname'])) . rand(1,1440);
                $user->password  = $this->createHash(12);
                $user->save();
            }
        }

        $validated['userid'] = $user->userid;

        if (empty($validated['email']) && !empty($user->email)) {
            $validated['email'] = $user->email;
        }


        $evententry = EventEntry::where('userid', $user->userid)
            ->where('eventid', $event->eventid)
            ->first();

        if (!empty($evententry)) {
            return back()->with('failure', 'Entry already exists');
        }


        if (empty($evententry)) {
            $evententry = new EventEntry();
        }

        // This handles the case where the first event competition is not an entry
        $divisionid = 0;
        if (is_array($validated['divisionid'])) {
            foreach ($validated['divisionid'] as $did) {
                if (is_numeric($did)) {
                    $divisionid = $did;
                    break;
                }
            }
        }

        $paymenttype = $request->input('paymenttype');

        $evententry->userid        = $validated['userid'];
        $evententry->eventid       = $event->eventid;
        $evententry->entrystatusid = 1; // 1 is pending
        $evententry->paid          = 0; // 0 is not paid yet
        $evententry->firstname     = !empty($validated['firstname'])      ? ($validated['firstname'])  : '';
        $evententry->lastname      = !empty($validated['lastname'])       ? ($validated['lastname'])   : '';
        $evententry->email         = !empty($validated['email'])          ? $validated['email']                  : '';
        $evententry->bib           = !empty($validated['bib'])            ? $validated['bib']                  : '';
        $evententry->address       = !empty($validated['address'])        ? ($validated['address'])    : '';
        $evententry->phone         = !empty($validated['phone'])          ? ($validated['phone'])      : '';
        $evententry->membership    = !empty($validated['membership'])     ? ($validated['membership']) : '';
        $evententry->notes         = !empty($validated['notes'])          ? ($validated['notes'])      : '';
        $evententry->clubid        = !empty($validated['clubid'])         ? intval($validated['clubid'])         : '';
        $evententry->divisionid    = $divisionid;
        $evententry->schoolid      = !empty($validated['schoolid'])       ? $validated['schoolid']               : '';
        $evententry->dateofbirth   = !empty($validated['dateofbirth'])    ? $validated['dateofbirth']            : '';
        $evententry->gender        = !empty(($validated['gender'] ?? '') == 'm')  ? 'm' : 'f';
        $evententry->country       = !empty($validated['country'])        ? ($validated['country'])              : '';
        $evententry->individualqualround = ($validated['individualqualround'] ?? 0);
        $evententry->teamqualround = ($validated['teamqualround'] ?? 0);
        $evententry->individualfinal = ($validated['individualfinal'] ?? 0);
        $evententry->teamfinal     = ($validated['teamfinal'] ?? 0);
        $evententry->mixedteamfinal = ($validated['mixedteamfinal'] ?? 0);
        $evententry->subclass      = ($validated['subclass'] ?? 0);
        $evententry->details       = $this->getRequestDetails($validated, ['mqs']);
        $evententry->enteredby     = Auth::id();
        $evententry->hash          = $this->createHash();
        $evententry->paymenttype   = ( !empty(($paymenttype) && in_array($paymenttype, ['bt','cc','other'])) ) ? $paymenttype : 'other';

        $evententry->save();

        // If the admin has said they wll pay by CC, Add it to THE USERS CC
        if ($paymenttype == 'cc') {
            $user = User::where('userid', $validated['userid'])->first();

            $entrycomps = [];
            $entrycompetitions = EntryCompetition::where('entryid', $evententry->entryid)->get();

            foreach ($entrycompetitions as $entrycompetition) {
                $entrycomps[$entrycompetition->eventcompetitionid] = $entrycompetition;
            }

            $user->addentrycartitem($event, $evententry, $entrycomps);
        }

        if (!$event->isNonShooting()) {
            $this->create($event, $evententry, $eventcompetition, $validated);
        }

        //SendEntryReceived::dispatch($evententry->email, $event->label);

        return redirect('/events/manage/evententries/' . $event->eventurl)->with('success', 'Entry Added!');

    }

    public function updateAdminRegistration(Request $request)
    {

        $event = Event::where('eventurl', $request->eventurl)->first();

        $user = User::where('userid', $request->input('userid'))->first();


        $eventcompetition = null;
        if ($event->isLeague()) {
            // get the event competition
            $eventcompetition = EventCompetition::where('eventid', $event->eventid)->first();

            if (empty($eventcompetition)) {
                return back()->with('failure', 'Please contact AOSA');
            }
        }


        if (empty($event) || empty($user)) {
            return back()->with('failure', 'Please try again later');
        }

        // Store the single event entry
        $evententry = EventEntry::where('userid', $user->userid)
                                ->where('eventid', $event->eventid)
                                ->first();

        $divisiondata = ($request->input('divisionid') ?? []);
        $divisiondata = array_combine($divisiondata, $divisiondata);


        // This handles the case where the first event competition is not an entry
        $divisionid = 0;
        foreach ($divisiondata as $did) {
            if (is_numeric($did)) {
                $divisionid = $did;
                break;
            }
        }
        $paymenttype = $request->input('paymenttype');


        // if the original entry WAS paying by CC, remove
        if (($evententry->paymenttype == 'cc') && $evententry->paymenttype != $paymenttype) {
            // Get the person who entered them AND themselves, remove from both in case
            $user_enteredby = User::where('userid', $evententry->enteredby)->first();
            $user_entered = User::where('userid', $evententry->userid)->first();

            $user_enteredby->removeentryfromcart($evententry->entryid);
            $user_entered->removeentryfromcart($evententry->entryid);
        }
        // original wasnt CC, but now is, ADD TO THEIR CART
        else if (($paymenttype == 'cc') && $evententry->paymenttype != $paymenttype) {
            $user_enteredby = User::where('userid', $evententry->enteredby)->first();

            $entrycomps = [];
            $entrycompetitions = EntryCompetition::where('entryid', $evententry->entryid)->get();

            foreach ($entrycompetitions as $entrycompetition) {
                $entrycomps[$entrycompetition->eventcompetitionid] = $entrycompetition;
            }

            $user_enteredby->addentrycartitem($event, $evententry, $entrycomps);
        }


        $evententry->firstname    = $request->input('firstname') ?? $evententry->firstname;
        $evententry->lastname     = $request->input('lastname') ?? $evententry->lastname;
        $evententry->email        = $request->input('email') ?? $evententry->email;
        $evententry->bib          = $request->input('bib') ?? $evententry->bib;
        $evententry->address      = $request->input('address') ?? $evententry->address;
        $evententry->phone        = $request->input('phone') ?? $evententry->phone;
        $evententry->membership   = $request->input('membership') ?? $evententry->membership;
        $evententry->notes        = $request->input('notes') ?? $evententry->notes;
        $evententry->clubid       = $request->input('clubid') ?? $evententry->clubid;
        $evententry->divisionid   = $divisionid;
        $evententry->schoolid     = $request->input('schoolid') ?? $evententry->schoolid;
        $evententry->gender       = !empty($request->input('gender') == 'm')  ? 'm' : 'f';
        $evententry->dateofbirth  = $request->input('dateofbirth') ?? $evententry->dateofbirth;
        $evententry->country      = $request->input('country') ?? $evententry->country;
        $evententry->individualqualround = $request->input('individualqualround') ?? $evententry->individualqualround;
        $evententry->teamqualround = $request->input('teamqualround') ?? $evententry->teamqualround;
        $evententry->individualfinal = $request->input('individualfinal') ?? $evententry->individualfinal;
        $evententry->teamfinal     = $request->input('teamfinal') ?? $evententry->teamfinal;
        $evententry->mixedteamfinal = $request->input('mixedteamfinal') ?? $evententry->mixedteamfinal;
        $evententry->subclass      = $request->input('subclass') ?? $evententry->subclass;
        $evententry->details       = $this->getRequestDetails(['mqs'=>$request->input('mqs')], ['mqs']);
        $evententry->paymenttype   = ( !empty(($paymenttype) && in_array($paymenttype, ['bt','cc','other'])) ) ? $paymenttype : 'other';

        $evententry->save();



        $entrycompetitions = EntryCompetition::where('userid', $user->userid)
                                                ->where('eventid', $event->eventid)
                                                ->where('entryid', $evententry->entryid)
                                                ->get();

        if ($event->isleague()) {

            foreach ($entrycompetitions ?? [] as $ecomp) {

                // if not in the original array, means they are removing the division entry
                if (!in_array($ecomp->divisionid, $divisiondata)) {
                    $ecomp->delete();
                    unset($divisiondata[$ecomp->divisionid]);
                }

                //if its still in the array, then leave as is
                else if (in_array($ecomp->divisionid, $divisiondata)) {
                    unset($divisiondata[$ecomp->divisionid]);
                }
            }

            // $entrycompetitions should now contain only those left to be added
            foreach ($divisiondata as $divisionid) {

                $entrycompetition = new EntryCompetition();
                $entrycompetition->entryid            = $evententry->entryid;
                $entrycompetition->eventid            = $event->eventid;
                $entrycompetition->userid             = $user->userid;
                $entrycompetition->competitionid      = '';
                $entrycompetition->eventcompetitionid = $eventcompetition->eventcompetitionid;
                $entrycompetition->divisionid         = $divisionid;
                $entrycompetition->competitionid      = '';
                $entrycompetition->roundid            = $eventcompetition->roundids;
                $entrycompetition->save();

            }
        }
        else if (!$event->isNonShooting()) {

            $neweventcomps = [];

            $roundids = $request->input('roundids');
            $divisionids = $request->input('divisionid');

            foreach ($roundids as $eventcompetition => $roundid) {
                $neweventcomps[$eventcompetition] = (object) [
                    'roundid' => $roundid,
                    'divisionid' => (!empty($divisionids[$eventcompetition])) ? $divisionids[$eventcompetition] : null
                ];
            }


            // Get the entrycompetition for the eventcomptition
            // if one exists, update the round/division
            // if not , create

            foreach ($entrycompetitions as $ec) {

                if (!in_array($ec->eventcompetitionid, array_keys($neweventcomps))) {
                    // means they have removed
                    // remove entry
                    $ec->delete();
                    // remove scores

                    // remove it from the array so it wont be added back
                    unset($neweventcomps[$ec->eventcompetitionid]);
                }
                else if (in_array($ec->eventcompetitionid, array_keys($neweventcomps))) {
                    $newentry = $neweventcomps[$ec->eventcompetitionid];

                    // if either of the values == remove
                    if ($newentry->roundid == 'remove' || $newentry->divisionid == 'remove')  {
                        $ec->delete();
                        unset($neweventcomps[$ec->eventcompetitionid]);
                        continue;
                    }

                    if (!empty($newentry->roundid) && !empty($newentry->divisionid)) {
                        $ec->roundid = $newentry->roundid;
                        $ec->divisionid = $newentry->divisionid;
                        $ec->save();

                        unset($neweventcomps[$ec->eventcompetitionid]);

                    }
                }
            }

            foreach ($neweventcomps as $eventcompetitionid => $newentry) {

                if (!empty($newentry->roundid) && !empty($newentry->divisionid)) {

                    $entrycompetition = new EntryCompetition();
                    $entrycompetition->entryid = $evententry->entryid;
                    $entrycompetition->eventid = $event->eventid;
                    $entrycompetition->userid = $user->userid;
                    $entrycompetition->competitionid = '';
                    $entrycompetition->eventcompetitionid = $eventcompetitionid;
                    $entrycompetition->divisionid = $newentry->divisionid;
                    $entrycompetition->roundid = $newentry->roundid;
                    $entrycompetition->save();

                }
            }
        }


        return back()->with('success', 'Entry Updated!');
    }


    public function createNonShootingRegistration(Event $event, Request $request)
    {

        $validated = $request->validated();

        $user = Auth::user();

        if ($validated['userid'] != $user->userid) {
            // make sure the person logged in can enter the person
            $user = UserRelation::where('userid', Auth::id())
                ->where('relationid', $validated['userid'])
                ->where('authorised', 1)
                ->first();

            if (!empty($user)) {
                $user = User::where('userid', $validated['userid'])->first();
            }
        }

        if (empty($event) || empty($user)) {
            return back()->with('failure', 'Please try again later');
        }

        // check to see if an entry exists for this user
        $existingevententry = EventEntry::where('eventid', $event->eventid)
            ->where('userid', $user->userid)
            ->first();

        if (!empty($existingevententry) ) {
            return back()->with('failure', 'An entry already exists, please check back in a few minutes');
        }


        $evententry = new EventEntry();
        $evententry->userid        = $request->input('userid');
        $evententry->eventid       = $event->eventid;
        $evententry->entrystatusid = 1; // 1 is pending
        $evententry->paid          = 0; // 0 is not paid yet
        $evententry->firstname     = !empty($validated['firstname'])      ? ($validated['firstname'])        : '';
        $evententry->lastname      = !empty($validated['lastname'])       ? ($validated['lastname'])         : '';
        $evententry->membership      = !empty($validated['membership'])       ? ($validated['membership'])         : '';
        $evententry->email         = !empty($validated['email'])          ? $validated['email']              : '';
        $evententry->address       = !empty($validated['address'])        ? ($validated['address'])          : '';
        $evententry->phone         = !empty($validated['phone'])          ? ($validated['phone'])            : '';
        $evententry->notes         = !empty($validated['notes'])          ? ($validated['notes'])            : '';
        $evententry->enteredby     = Auth::id();
        $evententry->hash          = $this->createHash();
        $evententry->save();


        SendEntryReceived::dispatch($evententry->email, $event->label);

        if ($event->adminnotifications) {
            SendEventAdminEntryReceived::dispatch($event->email,
                $event->label,
                $validated['email'],
                $evententry->firstname . ' ' . $evententry->lastname,
                $event->eventurl
            );
        }

        return redirect('/event/register/' . $event->eventurl)->with('success', 'Entry Received!');

    }

}
