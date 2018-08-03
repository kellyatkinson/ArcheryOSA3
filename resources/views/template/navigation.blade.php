<!-- Navigation Bar-->
<header id="topnav">
    <div class="topbar-main">
        <div class="container-fluid">

            <!-- Logo container-->
            <div class="logo">
                <!-- Text Logo -->
                <!--<a href="index.html" class="logo">-->
                <!--UBold-->
                <!--</a>-->
                <!-- Image Logo -->
                <a href="/" class="logo">
                    <img src="{{URL::asset('/images/Archeryosa.jpg')}}" alt="" height="28" class="logo-lg">
                    <img src="{{URL::asset('/images/Archeryosa.jpg')}}" alt="" height="20" class="logo-sm">
                </a>

            </div>
            <!-- End Logo container-->


            <div class="menu-extras topbar-custom">

                <ul class="list-inline float-right mb-0">

                    <li class="menu-item list-inline-item">
                        <!-- Mobile menu toggle-->
                        <a class="navbar-toggle nav-link">
                            <div class="lines">
                                <span></span>
                                <span></span>
                                <span></span>
                            </div>
                        </a>
                        <!-- End mobile menu toggle-->
                    </li>

                    <li class="list-inline-item dropdown notification-list">
                        <a class="nav-link dropdown-toggle waves-effect waves-light nav-user" data-toggle="dropdown" href="#" role="button"
                           aria-haspopup="false" aria-expanded="false">
                            <img src="{{URL::asset('/images/avatargrey.png')}}" alt="user" class="rounded-circle">
                        </a>
                        <div class="dropdown-menu dropdown-menu-right profile-dropdown " aria-labelledby="Preview">

                            @if(!Auth::check())
                                <a href="/login" class="dropdown-item notify-item">
                                    <i class="md md-account-circle"></i> <span>Login</span>
                                </a>
                                <a href="/register" class="dropdown-item notify-item">
                                    <i class="md  md-face-unlock"></i> <span>Register</span>
                                </a>
                            @else
                                <a href="/logout" class="dropdown-item notify-item">
                                    <i class="md md-settings-power"></i> <span>Logout</span>
                                </a>
                            @endif
                        </div>
                    </li>

                </ul>
            </div>
            <!-- end menu-extras -->

            <div class="clearfix"></div>

        </div> <!-- end container -->
    </div>
    <!-- end topbar-main -->

    <div class="navbar-custom">
        <div class="container-fluid">
            <div id="navigation">
                <!-- Navigation Menu-->
                <ul class="navigation-menu">

                    @if (Auth::check() && Auth::user()->scoringEnabled())
                        <li >
                            <a href="/scoring"><i class=" md-star"></i>Submit Scores!</a>
                        </li>
                    @endif

                    @if (Auth::check())
                        <li class="has-submenu">
                            <a href="#"><i class="md md-account-box"></i>My Account</a>
                            <ul class="submenu">
                                <li>
                                    <a href="/profile">Profile</a>
                                </li>
                                <li>
                                    <a href="/profile/myevents">My Events</a>
                                </li>
                                <li>
                                    <a href="/profile/myresults">My Results</a>
                                </li>
                            </ul>
                        </li>
                    @endif


                    <li class="has-submenu">
                        <a href="#"><i class="md-content-copy"></i>Results</a>
                        <ul class="submenu">
                            <li><a href="/events/previous">Event Results</a></li>
                            <li><a href="/records/nz">NZ Records</a></li>
                            <li><a href="/rankings/nz">NZ Rankings</a></li>
                        </ul>
                    </li>

                    <li class="has-submenu">
                        <a href="#"><i class="md-account-child"></i>Events</a>
                        <ul class="submenu">
                            <li><a href="/events/create">Create an Event</a></li>
                            <li><a href="/events">Event Registration</a></li>
                        </ul>
                    </li>

                    @if(Auth::check() && Auth::user()->roleid <= 3)
                        <li class="has-submenu">
                            <a href="#"><i class="md md-settings"></i>Admin</a>
                            <ul class="submenu megamenu">

                                @if(Auth::user()->roleid == 1)
                                    <li>
                                        <ul>
                                            <li>
                                                <span>Admin</span>
                                            </li>
                                            <li><a href="/admin/users">User Management</a></li>
                                        </ul>
                                    </li>
                                @endif

                                @if(Auth::user()->roleid <=2)
                                    <li>
                                        <ul>
                                            <li>
                                                <span>Setup</span>
                                            </li>
                                            <li><a href="/admin/clubs">Clubs</a></li>
                                            <li><a href="/admin/competitions">Competitions</a></li>
                                            <li><a href="/admin/divisions">Divisions</a></li>
                                            <li><a href="/admin/organisations">Organisations</a></li>
                                            <li><a href="/admin/rounds">Rounds</a></li>
                                        </ul>
                                    </li>

                                        <li>
                                            <ul>
                                                <li>
                                                    <span>Results</span>
                                                </li>
                                                <li><a href="#">Rankings</a></li>
                                                <li><a href="#">Results</a></li>

                                            </ul>
                                        </li>
                                @endif

                                <li>
                                    <ul>
                                        <li>
                                            <span>Events</span>
                                        </li>
                                        <li><a href="/events/manage">Manage Events</a></li>
                                    </ul>
                                </li>

                            </ul>
                        </li>
                    @endif

                </ul>
                <!-- End navigation menu -->
            </div> <!-- end #navigation -->
        </div> <!-- end container -->
    </div> <!-- end navbar-custom -->
</header>
<!-- End Navigation Bar-->