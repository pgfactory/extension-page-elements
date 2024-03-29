<?php

namespace PgFactory\PageFactoryElements;

use PgFactory\PageFactory\PageFactory;
use PgFactory\PageFactory\PfyForm;
use PgFactory\PageFactory\TransVars;
use function PgFactory\PageFactory\reloadAgent;
use function PgFactory\PageFactory\shieldStr;
use function PgFactory\PageFactory\mylog;

class Login
{
    private static $selfLink = './';
    private static $nextPage = './';
    private static $challengePending = false;
    private static $loginMode;

    /**
     * @param array $options
     * @return void
     */
    public static function init(array $options = []): void
    {
        if ($options['as-popup']??false) {
            $options['nextPage'] = './';
            self::$selfLink = './?login';
        }
        if (str_starts_with(self::$selfLink, './')) {
            self::$selfLink = PageFactory::$pageUrl . substr(self::$selfLink, 2);
        }
        $nextPage = ($options['nextPage']??false) ?: ($options['next']??false);
        if ($nextPage) {
            if (str_starts_with($nextPage, './')) {
                $nextPage = PageFactory::$pageUrl . substr($nextPage, 2);
            }
            self::$nextPage = $nextPage;
        }

        $defaultLoginMode = kirby()->option('pgfactory.pagefactory-elements.options.login-mode', 'login');
        self::$loginMode = ($options['mode']??false) ?: $defaultLoginMode;
    } // init


    /**
     * @return string
     * @throws \Exception
     */
    public static function render(): string
    {
        $wrapperClass = '';
        if ($username = PageFactory::$userName) {
            $html = <<<EOT
<div class='pfy-already-logged-in'>
    <p>{{ pfy-logged-in-as }} $username.<br>&nbsp;</p>
    <p><a class="pfy-button" href='./'>{{ pfy-back }}</a> <a class="pfy-button" href='./?logout'>{{ pfy-logout }}</a></p>
</div>
EOT;


        } else {
            switch (self::$loginMode) {
                case 'username-password-only':
                    $html = self::renderLoginForm();
                    $wrapperClass = 'pfy-login-unpw';
                    break;
                case 'passwordless':
                    $html = self::renderCombinedLoginForm();
                    $wrapperClass = 'pfy-login-otc';
                    break;
                default: // 'combined login'
                    $html = self::renderCombinedLoginForm();
                    $wrapperClass = 'pfy-login-unpw';
                    break;
            }
            if (self::$challengePending) {
                $wrapperClass = 'pfy-login-code';
            }
        }


        $html = <<<EOT
<div class='pfy-login-wrapper pfy-h-v-centered $wrapperClass'>
    <div>
$html
    </div>
</div><!-- /pfy-login-wrapper -->
EOT;

        $html = TransVars::resolveVariables($html);
        $html = shieldStr($html);
        PageFactory::$pg->addAssets('LOGIN');
        return $html;
    } // render


    /**
     * Renders a login firm that supports un-pw and passwordless login
     * @param string $message
     * @return string
     * @throws \Exception
     */
    private static function renderCombinedLoginForm(string $message = ''): string
    {
        $urlChangePw = PageFactory::$appUrl.'panel/reset-password';
        $labelChangePw = '{{ pfy-login-reset-pw }}';

        $formOptions = [
            'action'             => self::$selfLink,
            'showDirectFeedback' => false,
            'callback'           => function($data) { return self::loginCallback($data); },
            'wrapperClass'       => 'pfy-login-box',
            'formTop'            => $message,
        ];

        $formOptions['formTop']  .= '<span class="pfy-login-code">{{ pfy-login-enter-code-explanation }}</span>';

        $loginHeader   = '<h2 class="pfy-login-otc">{{ pfy-login-passwordless-header }}</h2>';
        $loginHeader  .= '<h2 class="pfy-login-unpw">{{ pfy-login-header }}</h2>';
        $loginHeader  .= '<h2 class="pfy-login-code">{{ pfy-login-enter-code-header }}</h2>';

        $labelLoginPwLess = '{{ pfy-login-with-otc }}';
        $infoEmail  = '<span class="pfy-login-unpw">{{ pfy-login-username-info }}</span>';
        $infoEmail .= '<span class="pfy-login-otc">{{ pfy-login-passwordless-info }}</span>';

        $labelLoginUnPw = '{{ pfy-login-default }}';

        $formElements = [
            'email'     => ['label' => 'E-Mail:',   'name' => 'pfy-login-email', 'type' => 'text', 'info' => $infoEmail, 'class' => 'pfy-email'],
            'password'  => ['label' => 'Password:', 'name' => 'pfy-login-password', 'type' => 'password', 'info' => '{{ pfy-login-otc-info }}'],
            'code'      => ['label' => 'Code:',     'name' => 'pfy-login-code',      'class' => 'pfy-login-code'],
            'cancel'    => ['next' => self::$nextPage],
            'subm-unpw' => ['type' => 'submit', 'label' => '{{ pfy-login-button }}', 'class' => 'pfy-login-unpw'],
            'subm-otc'  => ['type' => 'submit', 'label' => '{{ pfy-login-pwless-button }}', 'class' => 'pfy-login-otc'],
            'subm-code' => ['type' => 'submit', 'label' => '{{ pfy-login-code-button }}', 'class' => 'pfy-login-code'],
        ];

        $formOptions['formBottom'] = <<<EOT
<div class="pfy-login-mode-links">
<span class="pfy-login-pwless-link pfy-login-unpw"><a id="pfy-login-pwless" href="#">$labelLoginPwLess</a></span>
<span class="pfy-login-pw-link pfy-login-otc"><a id="pfy-login-pw" href="#">$labelLoginUnPw</a></span>
<span class="pfy-login-chpw-link"><a href="$urlChangePw">$labelChangePw</a></span>
</div>

EOT;
        // now render the form:
        $form = new PfyForm($formOptions);
        $html = $form->renderForm($formElements);
        $html = $loginHeader."\n$html";
        return $html;
    } // renderCombinedLoginForm


    /**
     * Renders a simple username/password login form
     * @param string $message
     * @return string
     * @throws \Exception
     */
    private static function renderLoginForm(string $message = ''): string
    {
        $urlChangePw = PageFactory::$appUrl.'panel/reset-password';
        $labelChangePw = '{{ pfy-login-reset-pw }}';

        $formOptions = [
            'action'             => self::$selfLink,
            'showDirectFeedback' => false,
            'callback'           => function($data) { return self::loginCallback($data); },
            'wrapperClass'       => 'pfy-login-box',
            'formTop'            => $message,
        ];

        $formElements = [
            'email'     => ['label' => 'E-Mail:',   'name' => 'pfy-login-email', 'type' => 'text'],
            'password'  => ['label' => 'Password:', 'name' => 'pfy-login-password', 'type' => 'password'],
            'cancel'    => ['next' => self::$nextPage],
            'submit'    => ['type' => 'submit', 'label' => '{{ pfy-login-button }}'],
        ];

        $formOptions['formBottom'] = <<<EOT
<div class="pfy-login-mode-links">
<span class="pfy-login-chpw-link"><a href="$urlChangePw">$labelChangePw</a></span>
</div>

EOT;
        // now render the form:
        $html = "<h2>{{ pfy-login-header }}</h2>\n";
        $form = new PfyForm($formOptions);
        $html .= $form->renderForm($formElements);
        return $html;
    } // renderLoginForm


    /**
     * Callback invoked from PfyForm, when login form gets evaluated.
     * @param array $data
     * @return string|false
     */
    private static function loginCallback(array $data): string|bool
    {
        $email = self::getUsersEmail($data);
        $password = $data['password']??false;
        $code = $data['code']??false;

        if ($code) {
            // 'code' received -> validate:
            try {
                kirby()->auth()->verifyChallenge($code);
                $str = self::renderMsg('pfy-login-success');
                mylog("Access code '$code' successfully verified", LOGIN_LOG_FILE);
                reloadAgent(self::$nextPage, $str);

            } catch (\Exception $e) {
                mylog("Access code '$code' failed");
                reloadAgent(self::$nextPage, '{{ pfy-login-failed }}', LOGIN_LOG_FILE);
            }

        } elseif ($password) {
            // 'password' received -> validate:
            try {
                kirby()->auth()->login($email, $password);
                $str = self::renderMsg('pfy-login-success', $email);
                mylog("$email successfully logged in", LOGIN_LOG_FILE);
                reloadAgent(self::$nextPage, $str);

            } catch (\Exception $e) {
                mylog("$email login failed", LOGIN_LOG_FILE);
                reloadAgent(self::$nextPage, '{{ pfy-login-failed }}');
            }

        } else {
            try {
                $status = kirby()->auth()->createChallenge($email, mode: 'login');
                if ($status->status() === 'pending') {
                    self::$challengePending = true;
                    mylog("Access code sent to '$email'", LOGIN_LOG_FILE);
                }
            } catch (\Exception $e) {
                mylog("Sending access code to '$email' failed", LOGIN_LOG_FILE);
                reloadAgent(self::$nextPage, '{{ pfy-login-failed }}');
            }
        }
        return false;
    } // loginCallback


    /**
     * @param string $str
     * @return string
     */
    private static function renderMsg(string $str, $username = ''): string
    {
        if (!$username) {
            $username = PageFactory::$userName;
        }
        $str = TransVars::getVariable($str);
        return str_replace('{{ username }}', $username, $str);
    } // renderMsg


    /**
     * @param array $data
     * @return string|false
     */
    private static function getUsersEmail(array $data): string|false
    {
        if (!$email = ($data['email']??false)) {
            return false;
        }
        if (!str_contains($email, '@')) {
            $users = kirby()->users();
            foreach ($users as $user) {
                $name = $user->name()->value();
                if ($name === $email) {
                    $email = $user->email();
                    break;
                }
            }
        }
        return $email;
    } // getUsersEmail

} // Login
