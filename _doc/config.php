
// 'auth.challenge.email.from' => 'webmaster@domain.net',

    'pgfactory.pagefactory-elements' => [
        'enableCoop'            => true,            // automatically inject HTTP header 'Cross-Origin-Opener-Policy: same-origin'
        'allowChangePassword'   => true,
        'login-mode'            => 'passwordless',
        'enableOnboardingAid'   => true,
        'formAutofillAssoc'     => [
            'vorname'           => 'given-name',
            'name'              => 'family-name',
            'nachname'          => 'family-name',
            'benutzername'      => 'username',
            'e_mail'            => 'email',
        ],
    ],


//    'pgfactory.pagefactory-elements' => [
//        'enableCoop' => true,            // automatically inject HTTP header 'Cross-Origin-Opener-Policy: same-origin'
//        'formAutofillAssoc' => [    // used in forms to apply 'autocomplete' attribute based on field names
//          'vorname'       => 'given-name',
//          'name'          => 'family-name',
//          'nachname'      => 'family-name',
//          'benutzername'  => 'username',
//          'e_mail'        => 'email', // applies to both e-mail and e_mail
//        ],
//        'templateCompilerDefaultMode' => 'twig', // default mode for TemplateCompiler, e.g. used by macro form()
//        'allowChangePassword'         => true,
//        'permitAccessCodeAsPassword'  => false,   // if not disabled, access-Code works as login password
//        'enableOnboardingAid'         => true, // enables the '?onboardingaid' feature
//        'initCode'                    => 'init.php', // run init code in site/custom/code/
//        'login-mode'                  => 'passwordless',   // 'username-password-only' or 'passwordless' or 'login'
//        'activatePresentationSupport' => true,
//        'presentationAutoSizing'      => true,
//        'autoSlideNumbering'          => true, // false, true or 'toc' (= first slide per page is TOC)
//        'presentationDefaultSize'     => '1.8vw',
//        'iframeAutoSizingChild'       => true, // activates iframe-resizer for iframe src (=child)
//        'iframeAutoSizingParent'      => true, // activates iframe-resizer for iframe parent
//    ],

