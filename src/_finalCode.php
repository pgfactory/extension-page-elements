<?php

namespace PgFactory\PageFactoryElements;

// handle ?onboardingaid:
//   => request later handled by Login::loginCallback()
if (isset($_GET['onboardingaid'])) {
    PageElements::renderOnboardingAid();
}
