<?php define("JSRT_VERSION", "0.1");  /* it's a non-binary version number */

/*
   phpjs runtime functions and data
   пппппппппппппппппппппппппппппппп
   Must be loaded to start the phpjs interpreter or to execute jsa::
   compiled sandbox code. Provides interfaces to PHP mathematical and
   string functions and hardened interfaces to regular expressions,
   and some other (js-only) utility code.
*/


#-- variable setting code
global $jsi_vars, $jsi_lvars, $jsi_funcs, $jsi_break;
$jsi_vars = (array)$jsi_vars;
$jsi_funcs = (array)$jsi_funcs;


#-- standard functions
foreach (
  array(
     "Math" => array(
        "abs", "acos", "asin", "atan", "ceil", "cos", "exp", "floor",
        "log", "man", "min", "pow", "random"=>"rand", "round", "sin",
        "sqrt", "tan",
     ),
     "system" => array(
        "time",
     ),
     "document" => array(
        "writeln"=>"jsrt_writeln", "write"=>"jsrt_write",
     )
  )
as
 $obj=>$d) {
   foreach ($d as $i=>$func) {
      $i = is_int($i) ? $func : $i;
      $jsi_vars[$obj][strtolower($i)] = $func;
      $jsi_funcs[] = $func;
   }
}


#-- functions aliases for basic features
$jsi_vars["write"] = "jsrt_write";
$jsi_vars["writeln"] = "jsrt_writeln";


#-- default and standard values
$jsi_vars["system"]["version"] = JS_VERSION;
$jsi_vars["Screen"]["width"] = 80;
$jsi_vars["Screen"]["height"] = 25;
$jsi_vars["Screen"]["pixelDepth"] = 4;
$jsi_vars["Screen"]["colorDepth"] = 4;




#-----------------------------------------------------------------
#-- run time functions

function jsrt_write($str="") {
   echo $str;
}
function jsrt_writeLn($str="") {
   echo $str .= "\n";
}


?>