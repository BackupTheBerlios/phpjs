<?php
/*
   phpjs accelerator
   ппппппппппппппппп
   This module converts the generated phpjs bytecode into sandboxed
   (safe/escaped) PHP code for fast execution of user supplied
   scripts.

   You need both "js.php" and "jsa.php" loaded at compile time. Use
   it this way:
   - $php_code = jsa_compile($js_source_code);
   - jsa_exec($php_code);
*/


define("JSA_FUNC_PREFIX", "jsa_ufnc_");



?>