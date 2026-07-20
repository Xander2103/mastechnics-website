<!DOCTYPE html>
<html lang="{{ $emailLocale ?? 'nl' }}">
<head>
    <meta charset="UTF-8">
    <title>@yield('subject')</title>
</head>
<body style="margin: 0; padding: 0; background: #f3f7fb; font-family: Arial, sans-serif; color: #102033;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background: #f3f7fb; padding: 28px 12px;">
        <tr>
            <td align="center">
                <table width="100%" cellpadding="0" cellspacing="0" style="max-width: 680px; background: #ffffff; border-radius: 18px; overflow: hidden; border: 1px solid #d9e4ef;">
                    <tr>
                        <td style="padding: 28px 32px; background: #0f3557; color: #ffffff;">
                            <p style="margin: 0 0 8px; font-size: 13px; font-weight: bold; letter-spacing: 0.08em; text-transform: uppercase;">
                                {{ config('site.name') }}
                            </p>

                            <h1 style="margin: 0; font-size: 26px; line-height: 1.25;">
                                @yield('heading')
                            </h1>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 30px 32px;">
                            @yield('content')
                        </td>
                    </tr>
                </table>

                <p style="margin: 18px 0 0; color: #8aa0b5; font-size: 12px;">
                    &copy; {{ date('Y') }} {{ config('site.name') }}
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
