<?php

namespace Usility\PageFactoryElements;

use Usility\PageFactory\PageFactory;
use Usility\PageFactory\PfyForm;
use Usility\PageFactory\TransVars;
use function Usility\PageFactory\reloadAgent;
use function Usility\PageFactory\shieldStr;
use function Usility\PageFactory\mylog;

class Login
{
    private static $selfLink = './';
    private static $nextPage = './';
    private static $challengePending = false;
    private static $loginHeader = '';
    private static $mode = 'login';

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

        self::$mode = ($options['mode']??false) ?: 'login';
    } // init


    /**
     * @return string
     * @throws \Exception
     */
    public static function render(): string
    {
        $wrapperClass = '';
        if ($user = kirby()->user()) {
            $html = '{{ pfy-logged-in-as }} '. $user->nameOrEmail();

        } else {
            $html = self::renderLoginForm();
            $wrapperClass = self::$challengePending? ' pfy-login-code' : '';
        }

        $html = <<<EOT
<div class='pfy-login-wrapper pfy-h-v-centered$wrapperClass'>
<div>
$html
</div>
</div>
EOT;

        $html = TransVars::resolveVariables($html);
        $html = shieldStr($html);
        PageFactory::$pg->addAssets('LOGIN');
        return $html;
    } // render


    /**
     * @param string $message
     * @return string|null
     * @throws \Exception
     */
    private static function renderLoginForm(string $message = ''): string|null
    {
        self::$loginHeader = '{{ pfy-login-header }}';
        $url2 = PageFactory::$appUrl.'panel/reset-password';
        $label2 = '{{ pfy-login-reset-pw }}';
        $url1 = urlAppendArg(self::$selfLink, 'login-otc');

        $info = '{{ pfy-login-username-info }}';

        $formOptions = [
            'action' => self::$selfLink,
            'showDirectFeedback' => false,
            'callback'           => function($data) { return self::loginCallback($data); },
            'wrapperClass'       => 'pfy-login-box',
        ];
        if ($message) {
            $formOptions['formTop']  = $message;
        }

        if (isset($_GET['login-otc'])) {
            $mode = 'login-otc';
        } elseif (isset($_GET['login-pw'])) {
            $mode = 'login-pw';
        } else {
            $mode = (self::$mode === 'passwordless') ? 'login-otc': 'login-pw';
        }

        if ($_POST['email']??false && !($_POST['password']??false)) {
            $formOptions['class']    = 'pfy-login-otc';
            $formOptions['formTop']  = '{{ pfy-login-enter-code-explanation }}';
            self::$loginHeader   = '{{ pfy-login-enter-code-header }}';
            $label1 = $label2 = '';

        } elseif ($mode === 'login-otc') {
            $formOptions['class']    = 'pfy-login-otc';
            self::$loginHeader   = '{{ pfy-login-passwordless-header }}';
            $url1 = urlAppendArg(self::$selfLink, 'login-pw');
            $label1 = '{{ pfy-login-default }}';
            $info = '{{ pfy-login-passwordless-info }}';

        } elseif ($mode === 'login-pw') {
            self::$loginHeader   = '{{ pfy-login-header }}';
            $url1 = urlAppendArg(self::$selfLink, 'login-otc');
            $label1 = '{{ pfy-login-with-otc }}';
        }

        $formElements = [
            'email'     => ['label' => 'E-Mail:',   'name' => 'pfy-login-email', 'type' => 'text', 'info' => $info, 'class' => 'pfy-email'],
            'password'  => ['label' => 'Password:', 'name' => 'pfy-login-password', 'type' => 'password', 'info' => '{{ pfy-login-otc-info }}'],
            'code'      => ['label' => 'Code:',     'name' => 'pfy-login-code',      'class' => 'pfy-login-code'],
            'cancel'    => ['next' => self::$nextPage],
            'submit'    => ['label' => '{{ pfy-login-button }}'],
        ];

        if ($label1) {
            $formOptions['formBottom'] = <<<EOT
<div class="pfy-login-mode-links">
<span class="pfy-login-pwless-link"><a href="$url1">$label1</a></span>
<span class="pfy-login-pw-link"><a href="$url2">$label2</a></span>
</div>

EOT;
        }
        $form = new PfyForm($formOptions);
        $form->createForm($formElements);
        $html = $form->renderForm();
        $html = self::$loginHeader."\n$html";
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
                $str = self::renderMsg('pfy-login-success');
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
    private static function renderMsg(string $str): string
    {
        $username = kirby()->user()->nameOrEmail();
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
