<?php define("JS_VERSION", "0.01010");  /* it's a binary version number */

/*
  the javascript interpreter for php
  ¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯
  Allows to execute PHP/JavaScript-lookalike code in a (safe) sandbox,
  where interfaces into the hosting interpreter are possible. So it may
  be useful to get embedded into CMS/Wiki engines, then users had the
  ability to extend a site without having direct and full access to the
  server resources.

  Exceptionally this is FreeWare and not PublicDomain. (Read: you
  can use it within and modify it for any other project, but replacing
  this paragraph with the GNU GPL comment is not allowed.)
  (c) 2004 WhoEver wants to. | <mario*erphesfurt·de>

  Interfaces
  ¯¯¯¯¯¯¯¯¯¯
   · js_exec($source_code)                 // simple all-in-one
   · $output = js($source_code)            // alternative

   · jsc::compile($source_code)            // compile into $bc
   · jsi::register_func($js_func_name, $php_func)
   · $jsi_vars["varname"] = "value"
   · jsi::run()                             // execute the loaded $bc


  things that are not (yet) implemented:
  -------------------------------------
  - expressions: (a ? b : c) still missing
  - function definitions (lambda and usual style)
  - object contexts and 'new' operator
  - local variable contexts
  - string expansion with PHP $variables and backslash escaping
  - switch/case construct
*/


// _NOTICEs should be turned off (you have been warned)
error_reporting(error_reporting() & (0xFFFF^E_NOTICE));


#-- a few settings
define("JS_PHPMODE", 0);   // not implemented
define("JS_CACHE", "/tmp/js");
define("JS_DEBUG", 0);
define("JS_FAST_CODE", !JS_DEBUG);


#-- language tokens (enable for more speed and less mem use)
if (JS_FAST_CODE) {
   define("JS_RT",	1);
   define("JS_OP_PFIX",	2);
   define("JS_FOREACH",	3);
   define("JS_OP_UNARY",	4);
   define("JS_QESTMARK",	5);
   define("JS_VALUE",	6);
   define("JS_ELSE",	7);
   define("JS_FOR",	8);
   define("JS_ASSIGN",	9);
   define("JS_ERROR",	10);
   define("JS_OP_BOOL_OR",	11);
   define("JS_MATH",	12);
   define("JS_WHILE",	13);
   define("JS_BREAK",	14);
   define("JS_INT",	15);
   define("JS_FCALL",	16);
   define("JS_OP_BIT",	17);
   define("JS_SWITCH",	18);
   define("JS_WORD",	19);
   define("JS_CMP",	20);
   define("JS_VAR",	21);
   define("JS_COLON",	22);
   define("JS_DO",	23);
   define("JS_COMMENT",	24);
   define("JS_BOOL",	25);
   define("JS_OP_BOOL_AND",	26);
   define("JS_OP_PLUS",	27);
   define("JS_PRINT",	28);
   define("JS_REAL",	29);
   define("JS_OP_MULTI",	30);
   define("JS_BRACE0",	31);
   define("JS_ELSEIF",	32);
   define("JS_BRACE1",	33);
   define("JS_CURLYBR0",	34);
   define("JS_CURLYBR1",	35);
   define("JS_COMMA",	36);
   define("JS_END",	37);
   define("JS_FUNCDEF",	38);
   define("JS_SQBRCKT0",	39);
   define("JS_SQBRCKT1",	40);
   define("JS_STR",	41);
   define("JS_OP_CMP",	42);
   define("JS_COND",	43);
   define("JS_CASE",	44);
   define("JS_IF",	45);
   define("JS_EOF",	255);
}






#------------------------------------------------------------------------
#-- simplified interface



function js_exec($codestr, $cleanup=0)
{
    #-- parse code into global $bc
    js_compile($codestr, $cleanup);

    #-- run interpreter
    jsi::run();
    //print_r($GLOBALS["bc"]);
    if ($cleanup) { $GLOBALS["bc"] = NULL; }
}


function js_compile($src="", $c=0) {
   return jsc::compile($src, $c);
}


function js($script=NULL) {
   global $bc;
   $r = NULL;

   #-- compile+cache or load
   if ($script) {
      $md5 = JS_CACHE."/".md5($script) . ".js.bc.gz";
      if (file_exists(JS_CACHE) && file_exists(JS_CACHE."/".$md5)) {
         $f = gzopen($md5, "rb");
         $bc = gzread($f, 1<<20);
         gzclose($f);
      }
      else {
         js_compile($script, "_CLEAN=1");
         $r = js();
         $f = gzopen($md5, "wb");
         fwrite($f, serialize($bc));
         gzclose($f);
      }
   }

   #-- exec, collect output
   if (!isset($r)) {
      ob_start();
      ob_implicit_flush(0);
      jsi::run();
      $r = ob_get_contents();
      ob_end_clean();
   }

   return($r);
}


#-- load other parts
$dir = dirname(__FILE__);
require("$dir/jsc.php");
require("$dir/jsi.php");
require("$dir/jsa.php");
require("$dir/jsrt.php");


?>