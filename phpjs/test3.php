<?php
#
# this demonstrates how to store the generated "bytecode"


$source = '

   writeLn("some smileys:");

   for ($x=0; $x<=175; $x+=3)
   {
      if ((x % 2) == 1) {
         print ":( ";
      }
      else {
         echo ":) ";
      }
   }

   writeLn("");
';


#-- phpjs
define("JS_DEBUG", 1);  # makes debug messages visible, when run first time
include("js.php");


#-- check for bytecode
$md5 = md5($source) . ".bc";
if (file_exists($md5)) {
   $bc = unserialize(implode("",file($md5)));
   jsi_mk_runtime_env();
   jsi_block($bc["."]);
}
else {
   js_exec($source);
}


#-- store bytecode (for later reuse)
$f = fopen($md5, "wb");
fwrite($f, serialize($bc));
fclose($f);


?>