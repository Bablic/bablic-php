# Bablic-php

How To Use:

1) Download "bablic.php" into your php index folder.

2) Place this snippet at the top of your index.php file:

```sh
<?php
# init the Bablci SDK with your site ID (required)
require 'bablic.php';
$site_id = 'your site id';
$bablic = new BablicSDK(
    array(
        'site_id'=> $site_id,
        // Optionally uncomment this line to set your website with subdir languages (/fr/, /it/,...)
        // before doing that add a rewrite rule to your server, or the translated links will return 404 from your server.
        // 'subdir'=>true 
    )
);

# run the SEO middlware
$bablic->handle_request(
    /*array(
        'nourl' => false, // optional
        'debug' => false, //optional
        'nocache' => false, //optional
        'url' => 'http://some.url.com' //optional
   )*/
);

# Example: how to paste the snippet in your HTML
?>
<html>
   <head>
       <title>A most unique website</title>
       <?php echo $bablic->bablic_top() ?>
       // rest of your HEAD html
   </head>
</html>
```
And that's it!

#Options

site_id - required, your Bablic site id.

nourl - Bablic will not rewrite URL links and will leave them as they are in the original language. Only use this option if you have rewriting the HTML links in your server.

debug - optional, when true Bablic SDK will handle ALL web requests

nocache - optional, when true does not use cache

url - optional, if you ever need to query Bablic for a diffrent domain (testing, staging, etc)

#Methods

handle_request() - request middleware, renders html optimized for crawling engines, only to crawling engines

bablic_top() - generates the snippet code to put in the page head tags
