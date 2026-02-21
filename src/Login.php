<?php

namespace PgFactory\PageFactoryElements;

use PgFactory\MarkdownPlus\Permission;
use PgFactory\PageFactory\Assets;
use PgFactory\PageFactory\Link;
use PgFactory\PageFactory\Page;
use PgFactory\PageFactory\PageFactory;
use PgFactory\PageFactory\PfyForm;
use PgFactory\PageFactory\TransVars;
use PgFactory\PageFactory\Utils;
use function PgFactory\PageFactory\isLocalhost;
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
        Assets::addAssets('LOGIN');
        $currPageUrl = PFY_PAGE_URL;
        if ($options['as-popup']??false) {
            $options['nextPage'] = $currPageUrl;
            self::$selfLink = "$currPageUrl?login";
        } elseif (!($options['nextPage']??false)) {
            $options['nextPage'] = $currPageUrl;
        }

        // check url for arg 'next':
        $session = kirby()->session();
        if ($_GET['next']??false) {
            $options['nextPage'] = $_GET['next'];
            $session->set('pfy.loginNextPage', $options['nextPage']);
        } elseif ($next = $session->get('pfy.loginNextPage')) {
            $options['nextPage'] = $next;
            $session->remove('pfy.loginNextPage');
        }

        $nextPage = ($options['nextPage']??false) ?: ($options['next']??false);
        if ($nextPage) {
            if (str_starts_with($nextPage, '~/')) {
                $nextPage = PFY_APP_BASE_URL . substr($nextPage, 2);
            }
            self::$nextPage = $nextPage;
        }

        $defaultLoginMode = kirby()->option('pgfactory.pagefactory-elements.login-mode', 'login');
        self::$loginMode = ($options['mode']??false) ?: $defaultLoginMode;
    } // init


    /**
     * @param string $message
     * @return string
     * @throws \Exception
     */
    public static function render(string $message = ''): string
    {
        $wrapperClass = '';
        if ($username = PageFactory::$userName) {
            if (kirby()->option('pgfactory.pagefactory-elements.allowChangePassword')) {
                $html = self::renderAccountAdminForm($username);
            } else {
                $html = self::renderLogoutForm($username);
            }

        } else {
            if (!self::checkUsersDefined()) {
                return '';
            }

            switch (self::$loginMode) {
                case 'username-password-only':
                    $html = self::renderLoginForm($message);
                    $wrapperClass = 'pfy-login-unpw';
                    break;
                case 'passwordless':
                    self::checkCodeLoginEnabled();
                    $html = self::renderCombinedLoginForm($message);
                    $wrapperClass = 'pfy-login-otc';
                    break;
                default: // 'combined login'
                    self::checkCodeLoginEnabled();
                    $html = self::renderCombinedLoginForm($message);
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

        $html = shieldStr($html);
        Page::applyRobotsAttrib();
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
        $formOptions = [
            'action'             => self::$selfLink,
            'showFeedbackInpage' => false,
            'class'              => 'pfy-form-colored',
            'dataReceivedCallback'           => function($data) {
                return self::loginCallback($data);
            },
            'wrapperClass'       => 'pfy-login-box',
            'formTop'            => "<span class='pfy-login-otc-unpw'>$message</span>",
            'problemWithFormBanner' => 'pfy-problem-with-login-banner',
        ];

        $formOptions['formTop']  .= '<span class="pfy-login-code">{{ pfy-login-enter-code-explanation }}</span>';

        $loginHeader   = '<h2 class="pfy-login-otc">{{ pfy-login-passwordless-header }}</h2>';
        $loginHeader  .= '<h2 class="pfy-login-unpw">{{ pfy-login-header }}</h2>';
        $loginHeader  .= '<h2 class="pfy-login-code">{{ pfy-login-enter-code-header }}</h2>';

        $labelLoginPwLess = '{{ pfy-login-with-otc }}';
        $infoEmail  = '<span class="pfy-login-unpw">{{ pfy-login-username-info }}</span>';
        $infoEmail .= '<span class="pfy-login-otc">{{ pfy-login-passwordless-info }}</span>';
        $infoOnCode  = '<span class="pfy-login-code">{{ pfy-login-code-info }}</span>';

        $labelLoginUnPw = '{{ pfy-login-default }}';

        $formElements = [
            'email'     => ['label' => '{{ pfy-login-email }}:',   'name' => 'pfyLoginEmail', 'type' => 'text', 'info' => $infoEmail, 'class' => 'pfy-email'],
            'password'  => ['label' => '{{ pfy-login-password }}:', 'name' => 'pfyLoginPassword', 'type' => 'password', 'info' => '{{ pfy-login-otc-info }}'],
            'code'      => ['label' => '{{ pfy-login-code }}:',     'name' => 'otp', 'info' => $infoOnCode, 'class' => 'pfy-login-code', 'autocomplete' => 'one-time-code'],
            'cancel'    => ['next' => self::$nextPage],
            'subm-unpw' => ['type' => 'submit', 'label' => '{{ pfy-login-button }}', 'class' => 'pfy-login-unpw'],
            'subm-otc'  => ['type' => 'submit', 'label' => '{{ pfy-login-pwless-button }}', 'class' => 'pfy-login-otc'],
            'subm-code' => ['type' => 'submit', 'label' => '{{ pfy-login-code-button }}', 'class' => 'pfy-login-code'],
        ];

        $formOptions['formBottom'] = <<<EOT
<div class="pfy-login-mode-links">
<span class="pfy-login-pwless-link pfy-login-unpw"><a id="pfy-login-pwless" href="#">$labelLoginPwLess</a></span>
<span class="pfy-login-pw-link pfy-login-otc"><a id="pfy-login-pw" href="#">$labelLoginUnPw</a></span>
</div>

EOT;
        // now render the form:
        $form = new PfyForm($formOptions);
        $html = $form->renderForm($formElements);

        // add js to check for OTP in SMS or Email:
        $js = <<<EOT
(async function startWebOTP() {
  if (!('OTPCredential' in window)) return;

  try {
    const controller = new AbortController();

    const otp = await navigator.credentials.get({
      otp: { transport: ['sms', 'email'] },
      signal: controller.signal
    });

    if (otp?.code) {
      const input = document.querySelector('#otp');
      input.value = otp.code;
      document.querySelector('form').submit();
    }
  } catch (err) {
    // Silent failure is correct
    console.debug('WebOTP not available', err);
  }
})();

EOT;
        Page::addJsReady($js);

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
        $urlChangePw = PFY_APP_BASE_URL.'panel/reset-password';
        $labelChangePw = '{{ pfy-login-reset-pw }}';

        $formOptions = [
            'action'             => self::$selfLink,
            'showFeedbackInpage' => false,
            'dataReceivedCallback'=> function($data) {
                return self::loginCallback($data);
            },
            'wrapperClass'       => 'pfy-login-box',
            'formTop'            => $message,
        ];

        $formElements = [
            'email'     => ['label' => 'E-Mail:',   'name' => 'pfyLoginEmail', 'type' => 'text'],
            'password'  => ['label' => 'Password:', 'name' => 'pfyLoginPassword', 'type' => 'password'],
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
     * @throws \Exception
     */
    private static function loginCallback(array $data): string|bool
    {
        $code = $data['otp']??false;
        if ($code) {
            // 'pfyLoginCode' received -> validate:
            try {
                kirby()->auth()->verifyChallenge($code);
                $user = Permission::getLoggedInUser();
                $email = $user->nameOrEmail()->value();
                if (!$email) {
                    mylog("Login Code '$code' failed", PFY_LOGIN_LOG_FILE);
                    reloadAgent(self::$nextPage, '{{ pfy-login-failed }}');
                }
                $str = self::renderMsg('pfy-login-success', $email);
                mylog("Login Code '$code' successfully verified", PFY_LOGIN_LOG_FILE);
                reloadAgent(self::$nextPage, $str);

            } catch (\Exception $e) {
                $errMsg = $e->getMessage();
                mylog("Login Code '$code' failed ($errMsg)", PFY_LOGIN_LOG_FILE);
                reloadAgent(self::$nextPage, '{{ pfy-login-failed }} '.$errMsg);
            }

        } elseif ($email = self::getUsersEmail($data)) {
            if ($password = ($data['pfyLoginPassword'] ?? false)) {
                // 'pfyLoginPassword' received -> validate:
                try {
                    // verify credentials:
                    kirby()->auth()->login($email, $password);
                    $str = self::renderMsg('pfy-login-success', $email);
                    mylog("$email successfully logged in", PFY_LOGIN_LOG_FILE);
                    reloadAgent(self::$nextPage, $str);

                } catch (\Exception $e) {
                    mylog("$email login failed", PFY_LOGIN_LOG_FILE);
                    reloadAgent(self::$nextPage, '{{ pfy-login-failed }}');
                }

            } else {
                try {
                    $status = kirby()->auth()->createChallenge($email, mode: 'login');
                    if ($status->status() === 'pending') {
                        self::$challengePending = true;
                        mylog("Login Code sent to '$email'", PFY_LOGIN_LOG_FILE);
                    }
                } catch (\Exception $e) {
                    mylog("Sending login code to '$email' failed", PFY_LOGIN_LOG_FILE);
                    reloadAgent(self::$nextPage, '{{ pfy-login-failed }}');
                }
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
        $user = Permission::getLoggedInUser();
        $username = $user->nameOrEmail()->value();
        $str = TransVars::getVariable($str);
        return str_replace('{{ username }}', $username, $str);
    } // renderMsg


    /**
     * @param array $data
     * @return string|false
     */
    private static function getUsersEmail(array $data): string|false
    {
        if ($email = ($data['pfyLoginEmail']??false)) {
            return Permission::findUsersEmail($email);
        }
        return false;
    } // getUsersEmail


    /**
     * @param mixed $username
     * @return string
     */
    private static function renderLogoutForm(mixed $username): string
    {
        $currPageUrl = PFY_PAGE_URL;
        $html = <<<EOT
<div class='pfy-already-logged-in'>
    <p>{{ pfy-logged-in-as }} $username.<br>&nbsp;</p>
    <p><a class="pfy-button" href='$currPageUrl'>{{ pfy-login-remain-loggedin }}</a> <a class="pfy-button" href='$currPageUrl?logout'>{{ pfy-logout }}</a></p>
</div>
EOT;
        return $html;
    } // renderLogoutForm


    /**
     * @param mixed $username
     * @return string
     */
    private static function renderAccountAdminForm(mixed $username): string
    {
        $urlChangePw = PFY_APP_BASE_URL.'panel/reset-password';
        $labelChangePw = '{{ pfy-login-reset-pw }}';
        $currPageUrl = PFY_PAGE_URL;
        $html = <<<EOT
<div class='pfy-already-logged-in'>
    <p>{{ pfy-logged-in-as }} $username.<br>&nbsp;</p>
    <div class="pfy-login-reset-pw"><a href="$urlChangePw">$labelChangePw</a></div>
    <div class="pfy-login-button-row"><a class="pfy-button" href='$currPageUrl'>{{ pfy-login-remain-loggedin }}</a> <a class="pfy-button" href='$currPageUrl?logout'>{{ pfy-logout }}</a></div>
</div>
EOT;
        return $html;
    } // renderAccountAdminForm


    /**
     * Checks whether 'auth' element is defined in config.php
     * @return void
     * @throws \Exception
     */
    private static function checkCodeLoginEnabled(): void
    {
        $authMethods = kirby()->option('auth.methods');
        $codeLoginEnabled = (is_array($authMethods) && in_array('code', $authMethods));
        if (!$codeLoginEnabled) {
            throw new \Exception('Warning: "code login" not enabled, please add "auth"-options to config.php');
        }
    } // checkCodeLoginEnabled


    /**
     * checks whether users have been defined. If not:
     *      throws exception on localhost, otherwise silently ignores the request
     * @return bool
     * @throws \Exception
     */
    private static function checkUsersDefined(): bool
    {
        if (!kirby()->users()->count()) {
            if (isLocalhost()) {
                throw new \Exception('Warning: no users defined yet');
            } else {
                return false;
            }
        }
        return true;
    } // checkUsersDefined

} // Login
