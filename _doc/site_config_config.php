

    'pgfactory.pagefactory-elements.options' => [
        'allowChangePassword' => true,
//        'initCode' => 'init.php',         // run init code in site/custom/code/
//        'login-mode' => 'passwordless',   // 'username-password-only' or 'passwordless' or 'login'
//        'activatePresentationSupport' => true,
//        'presentationAutoSizing'      => true,
//        'presentationDefaultSize'     => '1.8vw',
    ],

## User Self Admin:

Prerequisite:
site/blueprints/users/role.yml must contain:

---
permissions:
    access:
        account: true
        site: false
        languages: false
        system: false
        users: false
    user:
        changeEmail: true
        changeLanguage: false
        changeName: true
        changePassword: true
        changeRole: false
        delete: true
        update: true
---




Using config options:

    kirby()->option('pgfactory.pagefactory-elements.options.allowChangePassword');

