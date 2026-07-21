<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title> @yield('title')</title>
<link rel="icon" href="{{ asset('logo/logo.png') }}" sizes="any" />
<link rel="icon" type="image/png" sizes="192x192" href="{{ asset('logo/logo.png') }}" />
<link rel="icon" type="image/png" sizes="512x512" href="{{ asset('logo/logo.png') }}" />
<link rel="apple-touch-icon" href="{{ asset('logo/logo.png') }}" />
<link rel="shortcut icon" href="{{ asset('logo/logo.png') }}" type="image/png" />
<link rel="shortcut icon" href="/assets/compiled/png/logo.png" type="image/x-icon">
<link rel="stylesheet" href="/assets/extensions/bootstrap-icons/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="/assets/compiled/css/app.css">
@vite(['resources/css/evo-theme.css'])
<style>
    /* Critical shell layout to prevent first-paint flicker */
    #app1 > #main {
        margin-left: 5rem;
    }

    .shell-sidebar {
        position: fixed;
        left: 0;
        top: 0;
        width: 5rem;
        height: 100vh;
        z-index: 1040;
    }

    .shell-topbar {
        position: sticky;
        top: 0;
        z-index: 1020;
        margin-left: 5rem;
        width: calc(100% - 5rem);
    }

    @media (max-width: 991.98px) {
        #app1 > #main {
            margin-left: 0 !important;
        }

        .shell-topbar {
            margin-left: 0 !important;
            width: 100% !important;
        }

        .shell-sidebar {
            width: min(20rem, 86vw) !important;
            transform: translateX(-100%);
        }

        .shell-sidebar.is-mobile-open {
            transform: translateX(0);
        }
    }
</style>
@stack('shellStyles')
