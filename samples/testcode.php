<?php

require '../src/preprocessor.php';
require '../src/streamwrapper.php';

/*
$x = new \TheSeer\Tools\PreProcessor();
$res = $x->processString(file_get_contents('testfile.php'));
echo $res;
*/

stream_wrapper_register('ppp', '\TheSeer\Tools\PreProcessorStream');
\TheSeer\Tools\PreProcessorStream::setCachePath('/tmp');

include 'ppp://'.__DIR__.'/testfile.php';

