<?php define("JS_VERSION", "0.01010");

/*
  the javascript interpreter for php
  ¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯
  Allows to execute PHP/EcmaScript-lookalike code in a (safe) sandbox,
  where interfaces into the hosting interpreter are possible. So it may
  be useful to get embeded into CMS/Wiki engines, to give users the
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
   · jsi_register_func($js_func_name, $php_func)
   · $jsi_vars["any.name"] = "value"
   · jsi_run()                             // execute the loaded $bc


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

#-- regular expressions to detect tokens
$types = array(
   JS_REAL	=> '\d+\.\d+',
   JS_INT	=> '\d+',
   JS_BOOL	=> '(?i:TRUE|FALSE)',
   JS_WORD	=> '\$?[_A-Za-z]+(?:\.?[_\w]+)*',
   JS_STR	=> '(?:\"[^\"]*?\"|\'[^\']*?\')',
   JS_COMMENT	=> '(?:/\*.*?\*/|//[^\n]*)',
   JS_OP_CMP	=> '(?:[<>]=?|[=!]==?)',
   JS_ASSIGN	=> '(?:[-/%&|^*+:]=|=)',
   JS_OP_PFIX	=> '(?:\+\+|--)',
   JS_OP_MULTI	=> '[*/%.]',
   JS_OP_BOOL_AND => '&&',
   JS_OP_BOOL_OR => '\|\|',
   JS_OP_BIT	=> '[&|^]',
   // else $types1
);
$types1 = array(
   '+' => JS_OP_PLUS,
   '-' => JS_OP_PLUS,
   ';' => JS_END,
   '!' => JS_OP_UNARY,
   '~' => JS_OP_UNARY,
   '(' => JS_BRACE1,   // for braces, 1 means opening, 0 the closing one
   ')' => JS_BRACE0,
   '[' => JS_SQBRCKT1,
   ']' => JS_SQBRCKT0,
   '{' => JS_CURLYBR1,
   '}' => JS_CURLYBR0,
   ',' => JS_COMMA,
   '?' => JS_QESTMARK,
   ':' => JS_COLON,
   // else JS_ERROR,
);
$typetrans = array(
   JS_INT => JS_VALUE,
   JS_REAL => JS_VALUE,
   JS_STR => JS_VALUE,
);
$typetrans_word = array(
   "for" => JS_FOR,
   "foreach" => JS_FOREACH,
   "function" => JS_FUNCDEF,
   "while" => JS_WHILE,
   "do" => JS_DO,
   "break" => JS_BREAK,
   "if" => JS_IF,
   "else" => JS_ELSE,
   "elseif" => JS_ELSEIF,
   "switch" => JS_SWITCH,
   "case" => JS_CASE,
   "echo" => JS_PRINT,
   "print" => JS_PRINT,
);






#------------------------------------------------------------------------
#-- simplified interface



function js_exec($codestr, $cleanup=0)
{
    #-- parse code into global $bc
    js_compile($codestr, $cleanup);

    #-- run interpreter
    jsi_run();
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
      $md5 = JS_CACHE."/".md5($script) . ".bc.gz";
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
      jsi_run();
      $r = ob_get_contents();
      ob_end_clean();
   }

   return($r);
}


#-----------------------------------------------------------------------
#   ____ ___  __  __ ____ ___ _     _____ ____  
#  / ___/ _ \|  \/  |  _ \_ _| |   | ____|  _ \ 
# | |  | | | | |\/| | |_) | || |   |  _| | |_) |
# | |__| |_| | |  | |  __/| || |___| |___|  _ < 
#  \____\___/|_|  |_|_|  |___|_____|_____|_| \_\
#


#-- compiler part into its separate namespace
class jsc {


   #-- calls lexer and parser
   function compile($codestr, $cleanup=0)
   {
      #-- cut source code into lexograpic tokens
      jsc::lex($codestr);
      if (JS_DEBUG) {
         jsc::delex();
      }

      #-- parse into bytecode
      jsc::parse();
      if ($cleanup) { $GLOBALS["tn"] = NULL; }
   }



   #-- Cuts the input source text into more easily analyzeable chunks
   #   (tokens), each with a type flag associated.
   function lex($str) {

      global $types, $types1, $tn, $typetrans, $typetrans_word;

      $tn = array();

      #-- make large combined regex
      $regex = "#^(?:(" . implode(")|(", $types) . "))#";
      $type = array_keys($types);
      $typesnum = count($types);

      $str = trim($str);
      while ($str) {

         #-- split into tokens, guess type by regex
         if (preg_match($regex, $str, $uu)) {
            $val = $uu[0];

            $T = JS_ERROR;
            for ($i=1; $i<=$typesnum; $i++) {
               if (strlen($uu[$i])) {
                  $T = $type[$i-1];
                  break;
            }  }
         }
         #-- else it is an one-char token
         else {
            $val = $str[0];
            ($T = $types1[$val]) or ($T = JS_ERROR);
         }

         #-- unknown token
         if ($T == JS_ERROR) {
            jsc::err("cannot handle '".substr($str,0,10)."...'");
         }
         

         #-- strip found thingi away from input string
         $str = substr($str, strlen($val));


         #-- special cases to take care of in the lexer 
         switch ($T) {

            case JS_COMMENT:
               $str = ltrim($str);
               continue 2;
               break;

            case JS_STR:
               $val = substr($val, 1, strlen($val) - 2);
               break;

            case JS_WORD:
               $val = strtolower($val);
               if ($new = $typetrans_word[$val]) {
                  $T = $new;
                  $val = NULL;
               }
               while ($val[0] == "$") {
                  $val = substr($val, 1);
               }
               break;

            case JS_BOOL:
               $T = JS_INT;
               $val = (strlen($val) == 4) ?1:0;
               break;
            case JS_INT:
               $val = (int) $val;
               break;
            case JS_REAL:
               $val = (double) $val;
               break;
         }

         #-- valid language token
         if ($new = $typetrans[$T]) {
            $tn[] = array($new, $val, $T);
         }
         else {
            $tn[] = array($T, $val);
         }


         $str = ltrim($str);
      }
   }


   #-- prints the token streams` contents
   function delex() {
      global $tn, $bc;
      foreach ($tn as $data) {
         list($T, $str) = $data;
         if (!strlen($str)) { $str = $T; }
         echo "$str";
         if (($T==JS_END) or ($T==JS_CURLYBR0)) {
            echo "\n";
         }
      }
   }

   #-- prints the tokens (_DEBUG)
   function print_tokens($tn) {
      foreach ($tn as $i=>$d) {
         $t = strlen($d[0])<8 ? "\t" : "";
         echo "#$i\t$d[0]$t\t$d[1]\t$d[2]\n";
      }
   }





   #---------------------------------------------------------------------
     ######   #####  ######   #####  ####### ######
     ##   ## ##   ## ##   ## ##   ## ##   ## ##   ##
     ##   ## ##   ## ##   ## ##      ##      ##   ##
     ######  ####### ######   #####  #####   ######
     ##      ##   ## ####         ## ##      ####
     ##      ##   ## ## ##   ##   ## ##      ## ##
     ##      ##   ## ##  ##  ##   ## ##   ## ##  ##
     ##      ##   ## ##   ##  #####  ####### ##   ##
   #---------------------------------------------------------------------



   #-- get first entry from token stream
   function get() {

      global $type, $val, $next, $nextval,
         $jsp_i, $tn;

      $val = $nextval = false;
      $next = JS_EOF;

      if (isset($tn[$jsp_i])) {
         list($type, $val) = $tn[$jsp_i];
      }
      else {
         $type = JS_END;
      }
      if (isset($tn[$jsp_i+1])) {
         list($next, $nextval) = $tn[$jsp_i+1];
      }

      if (JS_DEBUG) {
         echo "@$jsp_i: t=$type,v=$val,n=$next,nv=$nextval\n";
      }

      $jsp_i++;
   }


   # get second entry from token stream, but as current $type
   #
   function getnext() {
      global $jsp_i;
      jsc::get();
      $jsp_i--;
   }


   #-- write an error message (better just collect and bail out later?)
   function err($s) {
      echo "\nPARSER ERROR: $s\n";
   }
   function bug($s) {
      jsc::err("this IS A BUG in phpjs: $s");
   }


   # compare current token type (and subtype),
   # put out an error message, if it does not match desired tags
   #
   function expect($t, $str=false, $caller=false) {
      global $type, $val, $next, $nextval, $jsp_i;
      if (($type != $t) || (is_array($t) && !in_array($type, $t)) ) {
   //           || ($str) && ($val != $str)
         if ($str) {
            $t = $str;
            $type = $val;
         }
         jsc::err("PARSE ERROR: '$t' expected, but '$type' seen @".($jsp_i-1)
                 . " by $caller");
      }
   }


   #-------------------------------------------------------------------------


   #-- start point for parsing given script
   function parse() {

      global $bc, $tn, $jsp_i;
      $jsp_i = 0;

      #-- initial mini transformations
      if (JS_DEBUG) {
         echo "\nall parsed \$tokens:\n";
         jsc::print_tokens($tn);
      }

      #-- array of expressions/commands
      $bc = array();

      #-- parse main program
      jsc::code_lines($bc["."]);

      if (JS_DEBUG) {
         echo "\ngenerated \$bytecode = ";
         print_r($bc);
      }
   }




   #---------------------------------------------------------------------
   #-- expressions

   /*
     following code uses a token look-ahead paradigm,
     where $next is examined, and (current) $type
     usually treaten as the left side argument of any
     expression
   */



   # <assignment> ::= <identifier> <assign_operator> <expr>
   #
   function assign(&$var) {
      if(JS_DEBUG) echo "_ASSIGN\n";
      global $type, $val, $next, $nextval;

      #-- left side (varname)
      $r = array(JS_ASSIGN);
      $r[] = $var;
      jsc::expect(JS_ASSIGN, 0, "assign");

      #-- combined assignment+operator
      $math = $val[0];
      if (($math == "=") || ($math == ":")) {
         $math = false;
      }

      #-- right side (expression)
      if ($math) {
         $r[] = array(JS_MATH, $r[1], $math, jsc::expr_start());
      } else {
         $r[] = jsc::expr_start();
      }

      return($r);
   }


   # <function_call> ::= <identifier> "(" (<expr> ("," <expr>)* )? ")"
   #
   function function_call() {
      if(JS_DEBUG) echo "_FCALL\n";
      global $type, $val, $next, $nextval;
      $r = array(JS_FCALL, $val);
      jsc::get();
      jsc::append_list($r, JS_BRACE0);
      jsc::get();
      jsc::expect(JS_BRACE0, ")", "function_call");
      return($r);
   }


   # adds var[expr][expr]
   #
   function array_var(&$var) {
      do {
         jsc::get();
         $var[] = jsc::expr_start();
         jsc::get();
         jsc::expect(JS_SQBRCKT0, "]", "_array_var");
      }
      while ($next == JS_SQBRCKT1);
   }


   # <var_or_func> ::= <idf> | <assignment> | <function_call> | <idf> (++|--)
   #
   function var_or_func() {
      global $type, $val, $next, $nextval;

      if(JS_DEBUG) echo "_VAR\n";
      jsc::expect(JS_WORD, 0, "var_or_func");

      #-- plain var
      $var = array(JS_VAR, $val);

      #-- array
      if ($next == JS_SQBRCKT1) {
         jsc::array_var($var);
      }

      #-- actual type
      if ($next == JS_BRACE1) {
         return(jsc::function_call());
      }
      elseif ($next == JS_ASSIGN) {
         jsc::get();
         return(jsc::assign($var));
      }
      elseif ($next == JS_OP_PFIX) {
         jsc::get();
         return
            array(JS_ASSIGN, $var, array(JS_MATH, $var, $val[0], 1));
      }
      else {
         return($var);
      }
   }


   # <pfix_var> ::=  (++ | --) <identifier>
   # are transformed into regular "var := var (+|-) 1" interpreter stream
   #
   function prefix_var() {
      global $val;
      $operation = $val[0];
      jsc::get();
      $var = jsc::var_or_func();   // bad: we shouldn't get a function here at all!
      if ($var[0] != JS_VAR) {   // (except if they may return references, hmm??)
         jsc::err("complex construct where variable reference expected @$GLOBALS[jsp_i]");
      }
      return
         array(JS_ASSIGN, $var,
            array(JS_MATH, $var, $operation, 1)
         );
   }


   # <expr_op_unary> ::=   "~" <value>  |  "!" <value>
   #
   function expr_op_unary() {
      global $type, $val, $next, $nextval, $jsp_i;
      switch ($val) {
         case "~":
         case "!":
         case "+":
         case "-":
            return array(JS_MATH, 0, $val, jsc::expr_value());
         default:
            jsc::bug("unary operator mistake");
      }
   }


   # <value> ::= "(" <expr> ")" | <var_or_func> | <constant> | <expr_op_unary>
   #
   function expr_value($uu=0) {
      global $type, $val, $next, $nextval, $jsp_i;

      jsc::get();
      switch ($type) {

         case JS_BRACE1:
            if(JS_DEBUG) echo "_(\n";
            jsc::expect(JS_BRACE1, "(", "_expr_value");
            $r = jsc::expr_start();
            jsc::get();
            jsc::expect(JS_BRACE0, ")", "_expr_value");
            return($r);
            break;

         case JS_OP_PFIX:
            return jsc::prefix_var();
            break;

         case JS_OP_UNARY:
         case JS_OP_PLUS:
            return jsc::expr_op_unary();
            break;

         case JS_WORD:
            return jsc::var_or_func();
            break;

         default:
            if(JS_DEBUG) echo "_CONST\n";
            jsc::expect(JS_VALUE, 0, "_expr_value");
            return(array(JS_VALUE, $val));
      }
   }




   # ABSTRACT <expr_math> ::=  <_value> | <_value> (OPERATOR) <_value>
   #
   function expr_math($num=0) {
      global $type, $val, $next, $nextval;

      #-- expression grammar
      #   (defines the precedence of operators)
      static $jsp_expr_math = array(
         JS_OP_BOOL_OR,
         JS_OP_BOOL_AND,
         JS_OP_BIT,
         JS_OP_PLUS,
         JS_OP_MULTI,
      );
      # <expr_multiply>  ::=  <_value> | <_value> (*|/|%) <_value>
      # <expr_plusminus> ::=  <_multiply> | <_multiply> (+|-) <_multiply>
      # <expr_bitop>     ::=  <_plusminus> | <_plusminus> (&|^|"|") <_plusminus>
      # <expr_booland>   ::=  <_bitop> | <_bitop> ("&&") <_bitop>
      # <expr_boolor>    ::=  <_booland> | <_booland> ("||") <_booland>
      //

      $upfunc = "expr_math";
      $OPERATOR = $jsp_expr_math[$num];
      $num++;
      if ($OPERATOR==JS_OP_MULTI) {
         $upfunc = "expr_value";
      }

      #-- get first expression
      $A = jsc::$upfunc($num);

      #-- check for (expected) operator
      if ($next == $OPERATOR) {
         $r = array(
            JS_MATH,
            $A,
         );
         while ($next == $OPERATOR) {
            jsc::get();
            $r[] = $val;   // +,- or *,/,% or &&,|| or
            $r[] = jsc::$upfunc($num);
         }
         return($r);
      }
      else {
         return($A);
      }
   }


   # <expr> ::= <_math> | <_math> (">=" | "<=" | "==" | ">" | "<" | "!=") <_math>
   #
   function expr_cmp() {
      global $type, $val, $next, $nextval;

      #-- get left side expression
      $A = jsc::expr_math();

      #-- check for comparision operator
      if ($next == JS_OP_CMP) {
         jsc::get();
         $r = array(
            JS_CMP,
            $A,
            $val,
         );
         $r[] = jsc::expr_math();
         return($r);
      }
      else {
         return($A);
      }
   }


   #   <expr> ::= <expr_plusminus>
   #
   function expr_start() {
      return jsc::expr_cmp();
   }





   #---------------------------------------------------------------------
   #-- language constructs

   /*
     unlike the expression code above, the following
     language construct analyzation functions don't
     have yet a filled-in $type, but in real called
     jsc::getnext() to have the values for the next
     token in $type and $val (pre-examine)

     therefore the language construct functions (except
     _block and _lines) usually start stripping the
     first token with jsc::get()
   */


   # extracts a comma separated list (of expressions)
   #
   function append_list(&$bc, $term=JS_END, $comma=JS_COMMA) {
      global $type, $val, $next, $nextval;
      while (($next!=$term) && ($next!=JS_EOF)) {
         $bc[] = jsc::expr_start();
         if ($next == $comma) {
            jsc::get();
         }
      }
   }


   # a break; statement
   #
   function constr_break(&$bc) {
      global $type, $val, $next, $nextval;

      #-- remove token
      jsc::get();   # "break"
      $r = array(JS_BREAK, 1);

      #-- ";" or expression/value follows
      if ($next != JS_END) {
         $r[1] = jsc::expr_start();
      }
      $bc = $r;
   }


   # chunk a for() loop
   #
   function constr_for(&$bc) {

      #-- remove tokens, get list (<expr>; <expr>; <expr>)
      jsc::get();   # "for"
      jsc::get();   # "("
      jsc::expect(JS_BRACE1, "(", "_constr_for0");
      $r = array();
      jsc::append_list($r, JS_BRACE0, JS_END);
      jsc::get();   # remove closing brace
      if (count($r) != 3) {
         jsc::err("there must be exactly three arguments in a for() loop");
      }

      #-- initial expression goes into the bc stream (before the JS_FOR entry)
      $bc[] = $r[0];
      $r[0] = JS_FOR;   # convert into bytecode stream for jsi_
      $r[3] = array();  # append code block
      jsc::block($r[3]);
      $bc[] = $r;       # output into stream
   }


   # if statement
   #
   function constr_if(&$bc) {
      global $type, $val, $next, $nextval;

      $r = array(JS_COND, JS_IF);  # if-conditional in bytecode

      #-- loop through if() and elseif() conditions and blocks
      while (($type==JS_IF) || ($next==JS_ELSEIF)) {

         #-- remove tokens
         jsc::get();   # "if" or "elseif" or "else"
         $is = $type;
         jsc::get();   # "("
         jsc::expect(JS_BRACE1, "(", "_constr_if");

         #-- generate bc stream
         $r[] = jsc::expr_start();
         $r[] = array();
         jsc::get();   # ")"
         jsc::expect(JS_BRACE0, ")", "_constr_if2");
         jsc::block($r[count($r)-1]);
      }
      
      #-- optional else block
      if ($type==JS_ELSE) {
         jsc::get();
         $r[] = array(JS_VALUE, 1);
         $r[] = array();
         jsc::block($r[count($r)-1]);
      }

      $bc[] = $r;
   }


   # while statement
   #
   function constr_while(&$bc) {
      global $type, $val, $next, $nextval;

      #-- remove tokens
      jsc::get();   # "while"
      jsc::get();   # "("
      jsc::expect(JS_BRACE1, "(", "_constr_while");

      #-- while-conditional in bytecode
      $r = array(
         JS_COND,
         JS_WHILE,
         jsc::expr_start(),
         array()    // placeholder
      );
      jsc::get();   # ")"
      jsc::expect(JS_BRACE0, ")", "_constr_while2");
      jsc::block($r[3]);

      $bc[] = $r;
   }


   # do statement
   #
   function constr_do(&$bc) {
      global $type, $val, $next, $nextval;
      #-- generate bc stream
      $r = array(
         JS_COND,
         JS_DO,
         0,        // placeholder
         array()   // placeholder
      );
      #-- remove tokens
      jsc::get();   # "do"
      $r[1] = array();
      jsc::block($r[3]);
      #-- while post condition
      jsc::get();   # "while"
      jsc::expect(JS_WHILE, false, "_constr_repeat");
      jsc::get();   # "("
      jsc::expect(JS_BRACE1, "(", "_constr_repeat2");
      $r[2] = jsc::expr_start();
      jsc::get();   # ")"
      jsc::expect(JS_BRACE0, ")", "_constr_repeat3");
      #-- add to parent bytecode stream
      $bc[] = $r;
   }


   # runtime/lang functions (echo, print)
   #
   function constr_rt(&$bc) {
      global $type, $val, $next, $nextval;
      $r = array(JS_RT, $type);
      jsc::get();
      jsc::append_list($r, JS_END);
      $bc[] = $r;
   }


   # reads one command/expr/line;
   #
   function code_lines(&$bc, $term=JS_END) {
      global $type, $val, $next, $nextval;

      jsc::getnext();
      while ($type && ($type!=$term)) {

         switch($type) {

            case JS_CURLYBR1:
               $bc[] = array();
               jsc::block($bc[count($bc)-1]);
               break;
            case JS_CURLYBR0:
               return;

            case JS_BREAK:
               jsc::constr_break($bc);

            case JS_FOR:
               jsc::constr_for($bc);
               break;

            case JS_IF:
               jsc::constr_if($bc);
               break;
            case JS_WHILE:
               jsc::constr_while($bc);
               break;
            case JS_DO:
               jsc::constr_do($bc);
               break;

            case JS_PRINT:
               jsc::constr_rt($bc);
               break;

            case JS_END:
               break;

            default:
               $bc[] = jsc::expr_start();
         }

         #-- end of line
         while ($next == JS_END) {
            jsc::get();
         }
         if ($type==JS_CURLYBR0) {
            jsc::getnext();
            return;
         }

         jsc::getnext();
      }
   }


   # parses a block of code
   #
   function block(&$bc, $term=JS_CURLYBR0) {
      global $type, $val, $next, $nextval;

      jsc::get();
      jsc::expect(JS_CURLYBR1, "{", "_block_{");

      $bc = array();
      jsc::code_lines($bc, $term);
   #echo "_P_BLOCK,$type,$next:\n";
   #print_r($bc);

      jsc::get();
      jsc::expect(JS_CURLYBR0, "}", "_block_}");
      jsc::getnext();
   }


} // end of class


#---------------------------------------------------------------------
#---------------------------------------------------------------------


       
#------------------------------------------------------------------------------
 ## ##   ## ###### ###### ######  ######  ######  ###### ###### ###### ######
 ## ###  ##   ##   ##     ##   ## ##   ## ##   ## ##       ##   ##     ##   ##
 ## #### ##   ##   ##     ##   ## ##   ## ##   ## ##       ##   ##     ##   ##
 ## ## ####   ##   ####   ######  ######  ######  ####     ##   ####   ######
 ## ##  ###   ##   ##     ## ##   ##      ## ##   ##       ##   ##     ## ##
 ## ##   ##   ##   ##     ##  ##  ##      ##  ##  ##       ##   ##     ##  ##
 ## ##   ##   ##   ###### ##   ## ##      ##   ## ######   ##   ###### ##   ##
#------------------------------------------------------------------------------


# runs the main program (in $bc["."])
#
function jsi_run()
{
   global $bc, $jsi_vars;
   jsi_mk_runtime_env();

   jsi_block($bc["."]);
}


# prepare variables
#
function jsi_mk_runtime_env()
{
   global $jsi_vars, $jsi_lvars, $jsi_funcs, $jsi_break;
   $jsi_lvars = (array)$jsi_lvars;
   $jsi_vars = (array)$jsi_vars;
   $jsi_funcs = (array)$jsi_funcs;
   $jsi_break = (int)0;
   $jsi_return = NULL;

   #-- fix "var.name" entries for new array(array)* structure
   foreach ($jsi_vars as $id => $val) {
      if (strpos($id, ".")) {
         unset($jsi_vars[$id]);
         $tmp = array(JS_VAR, $id);
         $tmp = &jsi_mk_var($tmp);
         $tmp = $val;
      }
   }

   #-- pre-def vars
   $jsi_vars["system"]["version"] = JS_VERSION;

   #-- allowed system (PHP) funcs
   $jsi_funcs[] = "jsrt_write";
   $jsi_funcs[] = "jsrt_writeLn";
   $jsi_funcs[] = "time";
   $jsi_vars["write"] = "jsrt_write";
   $jsi_vars["document"]["write"] = "jsrt_write";
   $jsi_vars["writeLn"] = "jsrt_writeLn";
   $jsi_vars["document"]["writeln"] = "jsrt_writeLn";
   $jsi_vars["system"]["time"] = "time";

   #-- std functions
   $add = array(
      "Math" => array(
         "abs", "acos", "asin", "atan", "ceil", "cos", "exp", "floor",
         "log", "man", "min", "pow", "random"=>"rand", "round", "sin",
         "sqrt", "tan",
      ),
   );
   foreach($add as $obj=>$d) {
      foreach ($d as $i=>$func) {
         $i = is_int($i) ? $func : $i;
         $jsi_vars[$obj][$i] = $func;
         $jsi_funcs[] = $func;
      }
   }

   #-- std values
   $jsi_vars["Screen"]["width"] = 80;
   $jsi_vars["Screen"]["height"] = 25;
   $jsi_vars["Screen"]["pixelDepth"] = 4;
   $jsi_vars["Screen"]["colorDepth"] = 4;

}


function jsi_register_func($js_f, $php_f)
{
   global $jsi_vars, $jsi_funcs;

   $jsi_vars[$js_f] = $php_f;  // functions are also variables/objects
   $jsi_funcs[] = $php_f;
}


function jsi_err($s)
{
   echo "\nINTERPRETER ERROR: $s\n";
}




#----------------------------------------------------------------------


# executes a block of commands
# (grouped into a subarray)
#
function jsi_block(&$bc)
{
   global $jsi_break, $jsi_return;

   $pc = 0;
   $pc_end = count($bc);
   for ($pc=0; $pc<$pc_end; $pc++) {

      if ($jsi_break) { 
         return;
      }

      if (is_array($bc[$pc]))  // else it is expression(/value) in void context
      switch ($bc[$pc][0]) {
         case JS_FCALL:
         case JS_ASSIGN:
         case JS_MATH:
         case JS_CMP:
         case JS_VALUE:
         case JS_VAR:
            jsi_expr($bc[$pc]);
            break;
         case JS_FOR:
            jsi_cn_for($bc[$pc]);
            break;
         case JS_COND:
            jsi_cn_cond($bc[$pc]);
            break;
         case JS_RT:
            jsi_cn_rt($bc[$pc]);
            break;
         case JS_BREAK:
            jsi_break($bc[$pc]);
            break;
         default:
            if (is_array($bc[$pc])) {
               jsi_block($bc[$pc]);
            }
            else {
               jsi_err("unknown processing code @$pc");
            }
      }
   }
}



#----------------------------------------------------------------------
#-- language constructs


function jsi_break(&$bc) {
   global $jsi_break;
   $jsi_break = jsi_expr($bc[1]);
}

function jsi_cn_for(&$bc) {
   global $jsi_break;
   while ($if=jsi_expr($bc[1])) {
      jsi_block($bc[3]);
      if ($jsi_break && $jsi_break--) { return; }
      jsi_expr($bc[2]);
   }
}


# conditional statements (if, while, ...)
#
function jsi_cn_cond(&$bc) {
   global $jsi_break;

   #-- if/elseif/else
   if ($bc[1]==JS_IF) {
      for ($i=2; $i<count($bc); $i+=2) {
         if (jsi_expr($bc[$i])) {
            jsi_block($bc[$i+1]);
            if ($jsi_break && $jsi_break--) { return; }
            break;   // execute always only one tree
         }
      }
   }
   #-- while
   elseif ($bc[1] == JS_WHILE) {
      while (jsi_expr($bc[2])) {
         jsi_block($bc[3]);
         if ($jsi_break && $jsi_break--) { return; }
      }
   }
   #-- repeat until / do while
   elseif ($bc[1] == JS_DO) {
      do {
         jsi_block($bc[3]);
         if ($jsi_break && $jsi_break--) { return; }
      }
      while (jsi_expr($bc[2]));
   }
}


# runtime functions (pseudo-func calls)
#
function jsi_cn_rt(&$bc) {
   global $jsi_vars;
   $args = array();
   for ($i=2; $i<count($bc); $i++) {
      $args[] = jsi_expr($bc[$i]);
   }
   switch ($bc[1]) {
      case JS_PRINT:
         echo implode("", $args);
         break;

      default:
         break;
   }
}



#----------------------------------------------------------------------
#-- variable handling


# create new variable in jsi context
#
function &jsi_mk_var(&$bc, $local_context=0)
{
   global $jsi_vars;

   #-- name.name.name
   $p = & $jsi_vars;
   foreach (explode(".", $bc[1]) as $i) {
      if (!isset($p[$i])) {
         $p[$i] = false;
      }
      $p = & $p[$i];
   }

   #-- additional array indicies
   if (isset($bc[2])) for ($in=2; $in<count($bc); $in++) {
      $i = jsi_expr($bc[$in]);
      if (!isset($p[$i])) {
         $p[$i] = false;
      }
      $p = & $p[$i];
   }
/*
echo "\n---VARcreateREFERENCE---\n";
print_r($bc);
echo "p=";
var_dump($p);
*/

   return($p);
}


# get contents of a jsi context variable
#
function jsi_get_var(&$bc)
{
   global $jsi_vars;

   #-- name.name.name
   $p = & $jsi_vars;
   foreach (explode(".", $bc[1]) as $i) {
      $p = & $p[$i];
   }

   #-- additional array indicies
   if (isset($p) && isset($bc[2])) for ($i=2; $i<count($bc); $i++) {
      $p = & $p[jsi_expr($bc[$i])];
   }
/*
echo "\n---VARget---\n";
print_r($bc);
echo "p=";
var_dump($p);
*/
   return($p);
}



#----------------------------------------------------------------------
#-- expressions


# runs a function (internal / external)
#
function jsi_fcall(&$bc)
{
   global $jsi_vars, $jsi_funcs;
   $r = 0;

   #-- a function is also a variable/object
   $var_bc = array(0, $bc[1]);
   if ($name = jsi_get_var($var_bc)) {

      #-- prepare func call arguments
      $args = array();
      for ($i=2; $i<count($bc); $i++) {
         if ($bc[$i][0] == JS_VAR) {
            $args[] = & jsi_mk_var($bc[$i]);    // pass-by-ref
         }
         else {
            $args[] = jsi_expr($bc[$i]);
         }
      }

      #-- system functions (PHP code)
      if (in_array($name, $jsi_funcs) && function_exists($name)) {
         $r = call_user_func_array($name, $args);
      }

      #-- inline functions
      // (in separate $bc)
      // ...

   }

   return($r);
}


# variable = assignment code
#
function jsi_assign(&$bc)
{
   $var = &jsi_mk_var($bc[1]);
   $var = jsi_expr($bc[2]);
   return($var);
}



# evaluate the pre-arranged (parser did it all) expressions
#
function jsi_math(&$bc)
{
   $constant = 1;
   $val = NULL;
   for ($i=0; $i<count($bc); $i+=2) {

      $add = jsi_expr($bc[$i+1]);
      $constant = $constant && is_scalar($bc[$i+1]);

      switch ($bc[$i]) {

         #-- initial value
         case JS_MATH:
            $val = $add;
            break;

         #-- basic math
         case "+":
            if ((!JS_PHPMODE) && (is_string($var) || is_string($add))) {
               $val .= $add;
            }
            else {
               $val += $add;
            }
            break;
         case "-":
            $val -= $add;
            break;
         case "*":
            $val *= $add;
            break;
         case "/":
            $val /= $add;
            break;
         case "%":
            $val %= $add;
            break;

         #-- bit
         case "&":
            $val &= $add;
            break;
         case "|":
            $val |= $add;
            break;
         case "^":
            $val ^= $add;
            break;
         #-- unary operator "~" (only two args, the first always zero, unused)
         case "~":
            $val = ~$add;
            break;

         #-- bool
         case "&&":
            $val = ($val && $add) ?1:0;
            break;
         case "||":
            $val = ($val || $add) ?1:0;
            break;
         case "!":  // unary operation, first argument zero and unused
            $val = (!$add) ?1:0;
            break;

         #-- string
         case ".":
            $val .= $add;
            break;

         #-- error
         default:
            jsi_err("expression operator '$bc[$i]' fault");
      }
   }
   if ($constant) {   // replace tree with constant
      $bc = $val;
   }
   return($val);
}


# does the boolean math
#
function jsi_cmp(&$bc)
{
   $val = 0;
   $A = jsi_expr($bc[1]);
   $B = jsi_expr($bc[3]);
   switch ($bc[2]) {
      case "<":
         $val = ($A < $B) ?1:0;
         break;
      case "<=":
         $val = ($A <= $B) ?1:0;
         break;
      case ">":
         $val = ($A > $B) ?1:0;
         break;
      case ">=":
         $val = ($A >= $B) ?1:0;
         break;
      case "===":
         $val = ($A === $B) ?1:0;
         break;
      case "==":
         $val = ($A == $B) ?1:0;
         break;
      case "!==":
         $val = ($A !== $B) ?1:0;
         break;
      case "!=":
         $val = ($A != $B) ?1:0;
         break;
      default:
         jsi_err("unknown boolean operation '$bc[2]'");
   }
   if (is_scalar($bc[1]) && is_scalar($bc[3])) {
      $bc = $val;
   }
   return($val);
}


# huh, simple
#
function jsi_expr(&$bc)
{
   if (is_array($bc)) {
      switch ($bc[0]) {
         case JS_ASSIGN:
            return jsi_assign($bc);
            break;
         case JS_MATH:
            return jsi_math($bc);
            break;
         case JS_CMP:
            return jsi_cmp($bc);
            break;
         case JS_VALUE:
            $bc = $bc[1];
            return jsi_expr($bc);
            break;
         case JS_VAR:
            return jsi_get_var($bc);
            break;
         case JS_FCALL:
            return jsi_fcall($bc);
            break;
         default:
            jsi_err("expression fault <<".substr(serialize($bc),0,128).">>");
      }
   }
   else {
      return($bc);   // must be direct value
   }
}


#-----------------------------------------------------------------
#-- run time functions

function jsrt_write($p="") {
   echo $p;
}
function jsrt_writeLn($p="") {
   echo $p;
   echo "\n";
}


#-----------------------------------------------------------------
#-- end


?>