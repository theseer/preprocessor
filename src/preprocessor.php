<?php
/**
 * Copyright (c) 2010 Arne Blankerts <arne@blankerts.de>
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without modification,
 * are permitted provided that the following conditions are met:
 *
 *   * Redistributions of source code must retain the above copyright notice,
 *     this list of conditions and the following disclaimer.
 *
 *   * Redistributions in binary form must reproduce the above copyright notice,
 *     this list of conditions and the following disclaimer in the documentation
 *     and/or other materials provided with the distribution.
 *
 *   * Neither the name of Arne Blankerts nor the names of contributors
 *     may be used to endorse or promote products derived from this software
 *     without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT  * NOT LIMITED TO,
 * THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 * PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER ORCONTRIBUTORS
 * BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * @package    PreProcessor
 * @author     Arne Blankerts <arne@blankerts.de>
 * @copyright  Arne Blankerts <arne@blankerts.de>, All rights reserved.
 * @license    BSD License
 * @link       http://github.com/theseer/preprocessor
 */

namespace TheSeer\Tools {

   /**
    * Implements a c++-alike preprocessor for php scripts
    *
    * @author     Arne Blankerts <arne@blankerts.de>
    * @copyright  Arne Blankerts <arne@blankerts.de>, All rights reserved.
    */
   class PreProcessor {

      /**
       * List of values set by "#define"
       *
       * @var array
       */
      protected $defines     = array();

      /**
       * Internal flag to signalize wether or not to skip over a section
       *
       * @var boolean
       */
      protected $skipOver    = false;

      /**
       * Output buffer
       *
       * @var string
       */
      protected $output      = '';

      public function processFile($path) {
         if (!file_exists($path)) {
            throw new PreProcessorException("'$path' not found.", PreProcessorException::NotFound);
         }
         return $this->processString(file_get_contents($path));
      }

      /**
       * Apply Preprocessing on given src string
       *
       * @param string $src
       *
       * @return string
       */
      public function processString($src) {
         $this->output  = '';
         $token   = token_get_all($src);
         $buffer  = '';

         foreach($token as $tok) {
            if (is_array($tok) && ($tok[0]==T_COMMENT) && ($tok[1][0]=='#')) {
               if ($this->handleDirective($tok[1])) continue;
            }

            if ($this->skipOver) {
               continue;
            }
            $this->output .= is_array($tok) ? $tok[1] : $tok;
         }
         return $this->output;
      }

      /**
       * Parse and handle a found directive
       *
       * @param string $dir Directive to handle
       *
       * @return boolean
       */
      protected function handleDirective($dir) {
         $candidate = substr(trim($dir),1);
         $parts = explode(' ', $candidate, 2);
         $method = strtolower($parts[0]).'Directive';

         if (method_exists($this,$method)) {
            return $this->$method( isset($parts[1]) ? $parts[1] : null );
         }
         return false;
      }

      /**
       * Handling code for '#define' statements
       *
       * @param string $payload Payload value for statement if given
       *
       * @return boolean
       */
      protected function defineDirective($payload) {
         if (!$this->skipOver) {
            if (is_null($payload)) return false;
            $def = explode(' ', $payload);
            if (isset($this->defines[$def[0]])) {
               throw new PreProcessorException("'{$def[0]}' cannot be redefined..", PreProcessorException::NoRedefine);
            }
            $this->defines[$def[0]] = substr($def[1],1,-1);
         }
         return true;
      }

      /**
       * Handling code for '#include' statements, replacing directive by file contents
       *
       * @param string $payload Payload value with filename to embed
       *
       * @return boolean
       */
      protected function includeDirective($payload) {
         if (!$this->skipOver) {
            if (is_null($payload)) return false;
            $this->output .= file_get_contents(substr($payload,1,-1));
         }
         return true;
      }

      /**
       * Handling code for '#elif' (else if) statements
       *
       * @param string $payload Payload value for statement if given
       *
       * @return boolean
       */
      protected function elifDirective($payload) {
         if (!$this->skipOver) {
            $this->skipOver = true;
            return true;
         }
         return $this->if($payload);
      }

      /**
       * Handling code for '#if' statements, using eval to allow custom php level code for evaluation
       *
       * @param string $payload Payload value for statement
       *
       * @return boolean
       */
      protected function ifDirective($payload) {
         if (is_null($payload)) return false;
         $this->skipOver = @eval('return (' . substr($payload,1,-1) . ') ? false : true;');
         return true;
      }

      /**
       * Handling code for '#ifdef' statements, testing if $payload is set, either as php constant or by #define earlier
       *
       * @param string $payload Payload value containing name of "constant" to test for
       *
       * @return boolean
       */
      protected function ifdefDirective($payload)  {
         if (is_null($payload)) return false;
         $this->skipOver = !(isset($this->defines[$payload]) || defined($payload));
         return true;
      }

      /**
       * Handling code for '#ifndef' statements, testing if $payload is NOT set, either as php constant or by #define earlier
       *
       * @param string $payload Payload value containing name of "constant" to test for
       *
       * @return boolean
       */
      protected function ifndefDirective($payload) {
         if (is_null($payload)) return false;
         $this->skipOver = (isset($this->defines[$payload]) || defined($payload));
         return true;
      }

      /**
       * Handling code for '#else' statements
       *
       * @param string $payload Additional values specified, though not used here
       *
       * @return boolean
       */
      protected function elseDirective($payload) {
         $this->skipOver = !$this->skipOver;
         return true;
      }

      /**
       * Handling code for '#endif' statements, ending a previously opened #if*
       *
       * @param string $payload Additional values specified, though not used here
       *
       * @return boolean
       */
      protected function endifDirective($payload) {
         $this->skipOver = false;
         return true;
      }

   }

   /**
    * Exception class for PreProcessor
    *
    * @author     Arne Blankerts <arne@blankerts.de>
    * @copyright  Arne Blankerts <arne@blankerts.de>, All rights reserved.
    */
   class PreProcessorException extends \Exception {
      const NotFound = 1;
   }

}
