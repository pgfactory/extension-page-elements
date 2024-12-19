<?php
/*
 * PageElements finalCode
 *  - fetch js files from js/
 *  - compile all instances of {{var}} with translated text
 *  - write result to assets/js/-xy.js
 */

namespace PgFactory\PageFactoryElements;

require_once __DIR__.'/compileJs.php';

compileJs();
