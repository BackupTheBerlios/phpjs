<?php
/*
   phpjs accelerator
   ¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯
   This module converts the generated phpjs bytecode into sandboxed
   (safe/escaped) PHP code for fast execution of user supplied
   scripts. It works almost like the ordinary interpreter but requires
   a bit glue code for secured (user-)function execution.

   You need both "js.php" and "jsa.php" loaded at compile time. Use
   it this way:
   - $php_code = jsa::assemble($js_source_code);
   
   Somewhen later a "jsrt.php" will be available to only provide the
   runtime dependencies for the sandboxed PHP code emitted by this
   accelerator.
   - jsrt::exec($php_code);
   For now, just use the jsi_mk_runtime_env() and eval($php_code).
*/


define("JSA_FUNC_PREFIX", "js_ufnc_");


#-- everything encapsulated into a static class (a namespace more or less)
class jsa {


   #-- transformation starts here
   function assemble($js_src="") {
      global $bc;
      
      #-- compile into $bc
      if ($js_src) {
         js_compile($src);
      }

      #-- functions
      $o = "\n/* compiled by phpjs accelerator */\n\n";
      foreach ($bc as $funcname=>$code) {
      
         #-- main code block
         if ($funcname==".") {
            $o .= jsa::block($bc[$funcname]);
         }
         else {
            $o .= jsa::func_def($funcname, $bc[$funcname]);
         }
      }

      #-- return string with php-sandbox code
      return($o);
   }


   #-- error while transforming into sandboxed PHP code
   function err($MSG) {
      die("jsa: $MSG");
   }
   
   
   #-- a block of code (expressions / language constructs)
   function block(&$bc) {
      $o = "{\n";
      
      for ($pc=0; $pc<=count($bc); $pc++) {
         if (is_array($bc[$pc]))  // else it is a plain value in void context
         switch ($bc[$pc][0]) {
           case JS_FCALL:
           case JS_ASSIGN:
           case JS_MATH:
           case JS_CMP:
           case JS_VALUE:
           case JS_VAR:
              $o .= jsa::expr($bc[$pc]) . ";\n";
              break;
           case JS_FOR:
              $o .= jsa::cn_for($bc[$pc]);
              break;
           case JS_COND:
              $o .= jsa::cn_cond($bc[$pc]);
              break;
           case JS_RT:
              $o .= jsa::cn_rt($bc[$pc]);
              break;
           case JS_BREAK:
              $o .= jsa::cn_break($bc[$pc]);
              break;
           default:
              if (is_array($bc[$pc])) {
                 $o .= jsa::block($bc[$pc]);
              }
              else {
                 jsa::err("unknown processing code @$pc");
              }
         }
      }
      
      $o .= "}\n";
      return($o);
   }


   #------------------------------------------------------------------
   #-- language constructs

   #-- break statement
   function cn_break(&$bc) {
      return(" break $bc[1];\n");
   }

   #-- for-loop
   function cn_for(&$bc) {
      return
         "/* was a FOR-loop */\n"
         . "while (" . jsa::expr($bc[1])  . ") {\n"
         . jsa::block($bc[3])
         . jsa::expr($bc[2]) . "\n}\n";
   }


   #-- conditional loops, code blocks
   function cn_cond(&$bc) {
      $o = "";

      #-- IF
      if ($bc[1]==JS_IF) {
         for ($i=2; $i<count($bc); $i+=2) {
            $o .= ($i >= 4 ? "elseif" : "if") . " ("
               . jsa::expr($bc[$i]) . ") "
               . jsa::block($bc[$i+1]);
         }
      }
      #-- WHILE
      elseif ($bc[1] == JS_WHILE) {
         $o .= "while (" . jsa::expr($bc[2]) . ")"
            . jsa::block($bc[3]);
      }
      #-- DO
      elseif ($bc[1] == JS_DO) {
         $o .= "do " . jsa::block($bc[3])
            . " while (" . jsa_expr($bc[2]) . ");\n";
      }

      return $o;
   }


   #-- runtime functions (pseudo-func calls)
   function cn_rt($bc) {
      array_shift($bc);
      $func = array_shift($bc);
      $o = "";
      foreach ($bc as $arg) {
         $arg = jsa::expr($arg);
         switch ($func) {
            case JS_PRINT:
               $o .= "print $arg;\n";
            default:
         }
      }
      return($o);
   }



   #-------------------------------------------------------------------
   #-- variable handling



   #-- always yields link into $jsi_vars[] array
   function variable(&$bc)
   {
      // does not handle special (old) name.name.name variables
      
      $varname = addslashes($bc[1]);
      $o = '$jsi_vars["' . $varname . '"]';

      #-- additional array indicies
      for ($i=2; isset($bc[$i]); $i++) {
         $o .= "[" . jsi_expr($bc[$i]) . "]";
      }

      return($o);
   }



   #-------------------------------------------------------------------
   #-- expressions


   #-- turn a function into a safety enhanced PHP code string
   function fcall(&$bc)
   {
      // "$tjfn" stands for temporary-javascript-function-name
      // needs to get more complicated for OO/type features

      $args = array(); 
      for ($i=2; isset($bc[$i]); $i++) {
         $args[] = jsa::expr($bc[$i]);
      }
      $args = implode(", ", $args);
      $pfix = JSA_FUNC_PREFIX;
      $variable = array(JS_VAR, $bc[1]);
      
      $o = '(($tjfn = ' . jsa::variable($variable)
         . ') ? ('
         . 'in_array($js_funcs, $tjfn) or (strpos($tjfn, '.$pfix.')===0)'
         . ' ? $tjfn('.$args.') : jsrt::error("forbidden function call")'
         . ') : NULL)';
      
      return $o;
   }


   #-- variable := assignment
   function assign(&$bc)
   {
      return
         "(" . jsa::variable($bc[1]) . "=" . jsa::expr($bc[2]) . ")";
   }



   #-- evaluate the pre-arranged (parser did it all) expressions
   function math(&$bc) {
      $o = "";
   
      #-- walk through contained elements
      for ($i=0; $i<count($bc); $i+=2) {

	 #-- current expression         
         $op = $bc[$i];
         $add = jsa::expr($bc[$i+1]);
        
         #-- change operator
         if (JS_PHPMODE && ($op=="+")) {
            // ooops, we cannot change that behaviour at will anymore
         }
         #-- unary operators are special
         elseif (($op == "~") or ($op == "!")) {
            $o = "$op$add";
            // the very first argument $bc[1] was zero
         }

         #-- very first element
         if ($op==JS_MATH) {
            $o .= $add;
         }
         else {
            $o .= " $op $add";
         }
      }

      return "($o)";
   }


   #-- the magic boolean math
   function cmp(&$bc)
   {
      $op = $bc[2];
      $A = jsa::expr($bc[1]);
      $B = jsa::expr($bc[3]);
      return "($A $op $B)";
   }


   #-- huh, even simpler than in the interpreter
   function expr(&$bc)
   {
      $o = "";
      if (is_array($bc)) {
         $type = $bc[0];
         switch ($type) {
            case JS_ASSIGN: return jsa::assign($bc);
            case JS_MATH:   return jsa::math($bc);
            case JS_CMP:    return jsa::cmp($bc);
            case JS_VALUE:  return jsa::expr($bc[1]);
            case JS_VAR:    return jsa::variable($bc);
            case JS_FCALL:  return jsa::fcall($bc);
            default:
              jsa::err("expression fault <<".substr(serialize($bc),0,128).">>");
         }
      }
      else {
         return($bc);   // must be literal value
      }
   }


} // end of class

?>