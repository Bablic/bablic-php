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
        'site_id'=> $site_id
    )
);

# run the SEO middlware
$bablic->handle_request(
    /*array(
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
   </head>
   <body>
       A most unique content
       
       <?php echo $bablic->bablic_bottom() ?>
   </body>
</html>
```
And that's it!

#Options

site_id - required, your Bablic site id.

debug - optional, when true Bablic SDK will handle ALL web requests

nocache - optional, when true does not use cache

url - optional, if you ever need to query Bablic for a diffrent domain (testing, staging, etc)

#Methods

handle_request() - request middleware, renders html optimized for crawling engines, only to crawling engines

bablic_top() - generates the snippet code to put in the page head tags

bablic_bottom() - generates the snippet code to put in the page bottom
