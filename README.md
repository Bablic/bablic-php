# Bablic-php

How To Use:
Place this snippet at the top of your index.php file.:
```sh
require 'Bablic_Sdk.php';
$site_id = 'your site id';
$bablic = new BablicSDK(
    array(
        'site_id'=> $site_id,
        'debug' => false, //optional
        'nocache' => false, //optional
        'url' => 'http://some.url.com' //optional
    )
);
$bablic->handle_request();
```
And that's it!

#Options

site_id - required, your Bablic site id.

debug - optional, when true Bablic SDK will handle ALL web requests

nocache - optional, when true does not use cache

url - optional, if you ever need to query Bablic for a diffrent domain (testing, staging, etc)

