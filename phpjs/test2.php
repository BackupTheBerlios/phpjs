<?php
#
# shows how to make use of PHP functions from within
# the scripts executed in the jsi_() sandbox


#-- script source code
$code = '

   x = 1.7;
   y = 1 + x * (5*x - 22*x*x) / 3;

   a = 0;
   for (n=1; n<=10; n+=1) {
      a = x * (a+n*5);
   }

   debug();

';
include("js.php");
js_compile($code);     // parse it


#-- enrich script context
jsi_mk_runtime_env();
jsi_register_func("debug", "jse_debug");
jsi_block($bc["."]);


#-- js-external funcs
function jse_debug() {
   print_r($GLOBALS["jsi_vars"]);
}


?>