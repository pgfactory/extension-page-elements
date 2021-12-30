<?php

function createHash( $hashSize = 8, $unambiguous = false, $lowerCase = false )
{
    if ($unambiguous) {
        $chars = UNAMBIGUOUS_CHARACTERS;
        $max = strlen(UNAMBIGUOUS_CHARACTERS) - 1;
        $hash = $chars[ random_int(4, $max) ];  // first always a letter
        for ($i=1; $i<$hashSize; $i++) {
            $hash .= $chars[ random_int(0, $max) ];
        }

    } else {
        $hash = chr(random_int(65, 90));  // first always a letter
        $hash .= strtoupper(substr(sha1(random_int(0, PHP_INT_MAX)), 0, $hashSize - 1));  // letters and digits
    }
    if ($lowerCase) {
        $hash = strtolower( $hash );
    }
    return $hash;
} // createHash
