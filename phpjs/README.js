
1 the php javascript interpreter
  ¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯
This interpreter provides an EcmaScript like programming language,
which can safely be embedded into applications for execution of user
supplied programs. This is possible because the executed code runs
in a safe sandbox then.
It is not feature complete and of course slower than running the
same programs in a real interpreter (rather than an interpreter in
another interpreter).

It makes use of techniques Jack W. Crenshaw layed out in his "Let's
build a compiler!" series, which are still available for download and
remain the most interesting lecture on that topic (see also UseNet
group news:comp.compilers).

NOTE: This is still VERY EARLY ALPHA (in development). For the list
of missing features, please see on top of the "js.php" library.


1.1 toc
    ¯¯¯
   1     introduction
   1.1   toc
   1.2   license
   1.3   why?

   2     interfaces
   2.1   bytecode buffering
   2.2   constants/settings

   3     extending
   3.1   adding functions
   3.2   adding variables

   4     how it works / internals
   4.1   parsing
   4.1.1 lexer
   4.2   the "bytecode"
   4.3   interpreter

   5     asorted notes
   5.1   examples
   5.2   optimizations
   5.3   speed comparisions
   5.4   changelog



1.2 license
    ¯¯¯¯¯¯¯
 It is FreeWare, which means that it CAN BE USED with/in most projects
 WITHOUT ANY RESTRICTIONS. It may be COPIED, MODIFIED, REDISTRIBUTED
 as long as this paragraph is preserved. This license cannot be auto-
 transformed into GNU GPL.

 A rewrite of the script in any other programming language (Perl or
 Python e.g.) of course frees it from any licensing issues or previous
 copyright ownerships. (You can then choose your own, as always.)


1.3 why?
    ¯¯¯¯
Hey, someone just had to do it!  ;)

The initial reasoning for this interpreter was to allow interchanging
of extension plugins between different WikiEngines, as it was an
neutral interchange language for the various PHP, Perl and Python
based implementations.

It should be possible to port this to Perl with ease (and even Python
seems feasible), once it is running. So this would clearly allow for
WikiFeatures:AutomaticFeatureInstall and other niceties.











2 interfaces
  ¯¯¯¯¯¯¯¯¯¯
The interpreter can be activated through different function calls. There
are combined feature calls, but the parser and interpreter parts can be
executed separately.


   js_exec($src)
   -------------
      Passes the given script string to the parser and then runs the
      interpreter. When the script runs all output will be directly
      output with 'echo'.
      Afterwards the "compiled" bytecode from the original script will
      still be in the global '$bc'.


   $output = js($src)
   ------------------
      Compiles and executes the given script code, but returns its
      output as result string. If no $src string was given, an already
      loaded '$bc' would be used instead.

      Also this call tries to buffer the bytecode in a compressed (.gz)
      serialized format. But see the next paragraph, on how to do this
      on your own.


   js_compile($src)
   ----------------
      Only compiled the given $src code string into the global '$bc'
      bytecode variable.


   jsi_run()
   ---------
      Executes the "bytecode" loaded in the global '$bc' variable. The
      output generated in that script (with print... or document.write)
      would be directly written to stdout/client (itself uses echo). The
      loaded $bc will be more optimized, once the interpreter run on it.


2.1 bytecode buffering
    ¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯
Instead of always passing the js_exec() function a string with source
code to get executed, you should buffer the generated $bc bytecode and
reuse it, if possible.  If you use the simplified js() call and set
JS_CACHE correctly, then this is taken care of on your behalf. If you
however would like to buffer the bytecode yourself, then you would
run the parser/compiler and interpreter parts yourself in individual
steps as follows:

    1)  pass the source code to:

          js_compile($string);

    2)  save the generated pre-bytecode from the global "$bc"
        variable (into a file or database)

    3)  Then execute the compiled program, by loading the
        pre-bytecode back into the global "$bc" variable,
        and calling:

          jsi_run();

        (Of course you wouldn't reload the bytecode from disk
        in the round you had actually generated it.)

As additional hint: You could first let the code run (with jsi_run, js_exec
or js_block), and afterwards save it. This way you would get the optimized
code from $bc (which is still available after return from the interpreter).


2.2 constants/settings
    ¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯
There is limited configurability in phpjs, but no real language behaviour
switch until yet (even if JS_PHPMODE suggests this).

JS_CACHE
  is the directory name, which the 'js()' call uses for storing bytecode
  for a given script string to later reuse it
  Note: this directory must usually be world-writable in a webserver
  environment (the shell/ftp thingy with "chmod 777 ..."); ALSO the
  directory should be cleaned if you upgrade from an earlier release,
  because the 'bytecode' constantly changes

JS_DEBUG
  enables a few print_r() and echo() statements throughout the code,
  mainly used for development of the phpjs lib

JS_FAST_CODE
  selects, if the JS_* constants should be defined()
  otherwise PHP will use the JS_* constant names as string scalars,
  which yields larger but more readable bytecode (= eventually slower)

JS_PHPMODE
  is not used yet






3 extending
  ¯¯¯¯¯¯¯¯¯
The goal of phpjs is to allow integration into existing CMS for
providing a sandboxed access to some of its features.

So, whenever you add functions or variables you should keep in mind,
not to comprimise your server security - or otherwise you could just
have used eval() instead of phpjs. Especially bare *sql_query()
functions shouldn't be made available from within the sandbox - rather
make multiple wrapper functions for accessing small fragments and this
read-only then.

The jsi_mk_runtime_env() function is automatically invoked by jsi_run()
and sets the most basic variables and functions. It however does not
clear $jsi_vars and $jsi_funcs, so you can extend both at any time
(even before a js_exec() call).

For example:
<code>
   $jsi_vars["myvar"] = 37.5;
   js("script...;");
</code>
Would run the given script with the variable "myvar" already defined.


3.1 adding variables
    ----------------
The global $jsi_vars contains an associative array of variable names
and values to be used inside the phpjs runtime/interpreter. You can
add variable names to it, before you invoke jsi_run() [or js_exec()
or others].

You can also create variable references therein, the interpreter and
PHP handle it transparently:

  $jsi_vars["_REQUEST"] = &$_REQUEST;

Would always keep the $_REQUEST array available inside the script for
access (=value reading) AND for modification (=changing _REQUEST values).


3.2 adding functions
    ¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯
Functions must be registered with $jsi_vars AND $jsi_funcs. The $jsi_funcs
array thereby lists the system (PHP) functions, the phpjs interpreter may
invoke when the running script requests it. But because functions in JS
are also objects/variables you also must list them in $jsi_vars,
associating the JS/script function name to the real PHP function name.

   $jsi_vars["myfunc"] = "php_function_name";
   $jsi_funcs[] = "php_function_name";

But you can also use the interpreter function registration call
(with the first parameter being the script function name, and the
second the real PHP function):

   jsi_register_func("js.my_func", "strip_tags");

From within a registered function, you can then of course modify the
"global" variables of the running script, by simply accessing $jsi_vars
again.

NOTE: parameter pass-by-ref is planned, but not yet implemented.







4 how it works / internals
  ¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯
The phpjs interpreter internally pre-converts scripts into a format very
suitable for quick execution of the defined program structures and its
associated data (instead of line-by-line matching and execution of
program source code).

The interpreter part heavily relies upon PHP managing most stuff, but
it does not let PHP directly execute the generated "bytecode" (which
actually should be called "parse tree", as it is not a real bytecode).


4.1 compiling the source code
    ¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯
The compilation process consists of separating the input string into
known language tokens (lexer), and then merging multiple tokens into
syntactic structures (parser), which finally make up the resulting
"bytecode". The (here) so called bytecode is in fact only an array of
arrays, with well-known identifiers the interpreter can more easily
take care of and can simply run through.


4.1.1 lexer
      ¯¯¯¯¯
A "lexer" [see also flex(1)] separates an input stream into small chunks,
and for example removes unnecassary whitespace from the initial source
code. The tokens are limited in number and clearly ease the further
analyzation of program structure.

Tokens are detected with regular expression matching, but types are often
changed later, after a more in-deep analyzation (or value convertion).

The "parser" then fetches one token after another with jsp_get() from
the global token variable $tn, which is simply a one-dimensional array
of token type number (or symbolic names in _DEBUG mode) and their
original or real (for constants) value.

As an example:
   array(
      array(JS_WORD, "a"),
      array(JS_ASSIGN, "="),
      array(JS_WORD, "a"),
      array(JS_OP_PLUS, "+"),
      array(JS_VALUE, 1.7, JS_REAL),
      array(JS_END, ";"),
   )
corresponded to the initial source code string '  a = a + 1.7;  '


4.1.2 parser
      ¯¯¯¯¯¯
The parser works with a simple (one-item) look ahead procedure (it knows
about the current token $type, and the $next one). This always is
sufficient to determine the program and expression structure from the
input token stream ($tn).

   Expression and language construct parsing work a bit differently, in
   that language construct (if, while, do, ...) analyzation works by
   examining the $next token type accidently filled into $type - which
   otherwise hold the current jsp_get() token type.

the expression parsing works as follows:
- jsp_expr_start() chains to the other expression look-ahead functions
- the highest _expr parser function actually retrieves the current
  element and must be a constant or variable or something that way
- all other _expr functions check for operator characters/tokens around
  constants or other expression constructs received from above, and else
  return() to the previous expression parsing functions
- so every parsing function either generates an array _including_ results
  from a function it called, or directly returns that result
- that way a tree (arrays of arrays) is returned, with multiply/division
  and addition/subtraction expressions grouped into separate array levels
  automatically (by the way the parser functions call each other)



4.2 the generated "bytecode"
    ¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯
The so called "bytecode" in reality is only a parse tree (a real compiler
would then transform it into flat bytecode or assembler source or even
machine code). It is an array of arrays, which the interpreter can easily
traverse in a very functional and recursive fashion (easy! easy! easy!).

In the global '$bc' variable, that always contains that bytecode, you have
first a level of function name blocks, with the name "." being reserved
for the main program. Other $bc entries were user defined functions (but
their names may not always match the function name in the source code).

The interpeter usually starts examing the $bc["."] sub array. An entry
is often another array, with its first [0] element determining the
type of function (either an expression or a language construct).

But to simply make an example:
 $bc["."] = array(
    0 => array(
       JS_ASSIGN,
       array(JS_WORD, "a"),
       array(
          JS_MATH,
          array(JS_WORD, "a"),
          "+",
          array(JS_VALUE, 1.7, JS_REAL),
       ),
    ),
    1 => array(
       JS_RT,
       JS_PRINT,
       array(JS_WORD, "a"),
    ),
 );

Would correspond to the simple source:
<code>
  a = a + 1;     // or simply a++;
  echo a;
</code>

You may find it interesting to bail out the generated '$bc' variable
using PHPs` print_r() function for some of your scripts from time to
time.
Note that the bytecode gets optimized (JS_VALUEs are collapsed) after
the interpreter run it.



4.3 interpreter
    ¯¯¯¯¯¯¯¯¯¯¯
All bytecode pieces either are expressions (JS_MATH, JS_CMP, JS_ASSIGN,
JS_VALUE, JS_WORD, JS_FCALL, ...) handled by the mighty jsi_expr()
function, or language constructs (like JS_IF, JS_FOR, ...) where a
handler exists for each.

The interpeters jsi_block() so simply starts looking at each entry of the
$bc["."] array, and simply goes down the tree for sub arrays (which often
were { code blocks } then). The language constructs have a well defined
number of arguments, and the expressions (often arguments for constructs)
can be nested without any limit - jsi_expr() takes care of this.

Most expressions are of the JS_MATH token type, and handled in jsi_math().
But as the parser already separated plus/minus and division/multiply into
different array levels, they would never occour in one JS_MATH expression
sub array at the same time. Therefore it is irrelevant that there is
basically only js_math() and js_cmp() taking care of most expressions.


...








5 asorted notes
  ¯¯¯¯¯¯¯¯¯¯¯¯¯
global variables are used heavily:
   $tn - is the list of tokens, the lexigraphic analyzer emitted
   $type,$next,$val,$nextval - contain tokens from the $tn list
   $jsp_i - counter/position for the token list   
   $bc - contains the "bytecode" (in reality only a parse tree)
   $jsi_vars - global variable scope of the interpreter
   $jsi_lvars - stack of local variables (inside functions)
   $jsi_sthis - stack of object references (this.)
   $jsi_funcs - accessible host interpreter functions

implementation details:
  - the $bc is optimized for the interpreter (which itself adapts the
    stream at run time) and must not always correspond to the original
    script code
  - in the parser things like "a+=1" are especially transformed into
    "a=a+1" to reduce effort in the interpreter part
  - the $jsi_vars should be an (array of)+ arrays, by splitting
    variable names at every full stop "."
  - a few PHP and Pascal language constructs will be supported
    (especially PHP variable names are already)
  - the most work is done by the hosting interpreter (PHP)
  - a separate token->lineno association array could be introduced
    (instead of lineno tokens in the stream), to make error messages
    more useful to users

implementation decisions:
  - eval() won't be implemented - while it may be possible to make it
    work with some workarounds for the multiple global variables used
    by compiler and interpreter

random notes:
  - the constants (JS_*) don't need to be defined, as PHP automatically
    makes strings of them, so it gives more readable "pre-bytecode";
    which on the other hand is slightly slower (see also JS_DEBUG and
    JS_FAST_CODE settings)
  - yes, phpjs version numbers are binary ;-)

future project goals:
  - make this interpreter run itself
  - port the Perl operating system



5.1 examples
    ¯¯¯¯¯¯¯¯
The interpreter accepts JavaScript-lookalike code:

   js_exec('
      for (i=0; i<5; $i+=1) 
      {
         document.writeLn("counting up," + " i = " + i);
      }
   ');

But also PHP-like variables mixed in:

   jsi_exec('

      $mytime = system.time;
      x = 1;

      if (x == $x) {
         document.write("The time is: ");
         document.writeLn( $mytime() );
      }

   ');

But please also note the list of missing features on top of the "js.php"
script.


5.2 optimizations
    ¯¯¯¯¯¯¯¯¯¯¯¯¯
Source will initially be transformed into an intermediate representation,
a parse tree optimizied (or at least suiteable) for quick (or at least
simple) interpretation (or at least something that way). This "pre-bytecode"
should be stored for later reuse by the embeding application, to prevent
reparsing the scripts each time they were to be run.

It is possible to optimize the generated pre-bytecode. It is further
possible to make real bytecode of it (Parrot, P-Code), though that may
not yield better results at all, because the current way of doing things
in reality lets the PHP engine do the real work (type handling and
auto-conversion are almost like in JavaScript), what is rather speedy.

It already features automatic code optimization during run time (the
pseudo-bytecode gets smaller whenever the interpreter detects
redundancies).


5.3 speed comparisions
    ¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯¯
These test results are not representative and will always differ.
A minimal test script with two loops revealed following execution
speed differences:

unbuffered executiton through js_exec()
    PHP 4.3 as basis
    PHP 5b  was around  4% faster than 4.3
    PHP 4.1 was around 21% faster than 4.3

BUFFERED execution was only around 16% FASTER THAN UNBUFFERED
    PHP 4.3 as basis
    PHP 5b  was around  4% faster than 4.3
    PHP 4.1 was around 23% faster than 4.3

So the tests revealed not only, that this JavaScript interpreter
is SlowSlowSlow, but also that PHP 4.3 is the slowest PHP ever.

And it also turned out, that the parser/lexer is in fact much
faster than the interpreter (what happens really rarely for
compiler stuff), in that it only makes 1/6 of the overall execution
time for a given script. Though the initial tests may again not
count for real world applicitons.



5.4 changelog
    ¯¯¯¯¯¯¯¯¯
0.00001
- tokenizer and simple expression parsing work

0.00010
- PHP function calls enabled
- interpreter working
- license changed to FreeWare
- added if() statement (and JS_CMP expression support in interpreter),
  but also runtime/language functions

0.00011
- math expression parsing packaged into one function, recursive calls
  driven by grammar data
- introduction of numeric JS_ symbols (clean bytecode, faster execution)
- some notes, inline doc

0.00100
- while and do constructs
- more inline comments and explainations

0.00101
- initial README
- first public release

0.00110
- unary operators ++ and -- are now supported (converted into 'var:=var+1')
- also ~ and ! work now
- README was overhauled
- simplified interface 'js()' with caching support was added

0.00111
- $jsi_vars is now an array of arrays (for vars containing dots), what
  now allows introduction of language array support
- JS_WORD was changed into JS_VAR - but only for the interpreter part
- release of addons/
- minimal array support (for var names and assignments)

0.01000
- break; statement added
- faster lexer, more precise token types (separated opening and closing braces)
- allowed for unary + and - (but very inefficient for constants)

0.01001
- parameters to registered functions are passed by reference now, generally


