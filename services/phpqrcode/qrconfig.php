<?php
/*
 * PHP QR Code encoder
 *
 * Config file, feel free to modify
 */
     
    // LOKALE ANPASSUNG (P-2026-08-08-04): Cache abgeschaltet.
    // Das Cache-Verzeichnis liegt innerhalb des Codebaums und existiert nicht.
    // Ergebnis waren bei jeder QR-Erzeugung zahlreiche file_put_contents-/mkdir-
    // Warnungen. Masken werden jetzt im Speicher berechnet - bei der geringen
    // Zahl erzeugter Codes (nur beim Speichern einer Maschine) unerheblich, und
    // der Webserver braucht keine Schreibrechte im Programmverzeichnis.
    // Bei einem Bibliotheks-Update bitte wieder mit anpassen.
    define('QR_CACHEABLE', false);                                                              // use cache - more disk reads but less CPU power, masks and format templates are stored there
    define('QR_CACHE_DIR', dirname(__FILE__).DIRECTORY_SEPARATOR.'cache'.DIRECTORY_SEPARATOR);  // used when QR_CACHEABLE === true
    define('QR_LOG_DIR', dirname(__FILE__).DIRECTORY_SEPARATOR);                                // default error logs dir   
    
    define('QR_FIND_BEST_MASK', true);                                                          // if true, estimates best mask (spec. default, but extremally slow; set to false to significant performance boost but (propably) worst quality code
    define('QR_FIND_FROM_RANDOM', false);                                                       // if false, checks all masks available, otherwise value tells count of masks need to be checked, mask id are got randomly
    define('QR_DEFAULT_MASK', 2);                                                               // when QR_FIND_BEST_MASK === false
                                                  
    define('QR_PNG_MAXIMUM_SIZE',  1024);                                                       // maximum allowed png image width (in pixels), tune to make sure GD and PHP can handle such big images
                                                  