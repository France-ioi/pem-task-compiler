<?php
/* Copyright (c) 2012 Association France-ioi, MIT License http://opensource.org/licenses/MIT */

class PEMTaskCompiler
{
   private $path;
   private $bebras;
   private $taskKey;
   // The base directory of the tasks
   private $taskDir;
   
   // Bitmask
   const TASK = 2;
   const SOLUTION = 4;
   const GRADER = 8;
   const PROXY = 16;
   // stdButtonsAndMessages
   const DISPLAY = 32;
   // stdAnsTypes
   const SAT = 64;
   // if this bit is present, take only modules of other set bits
   const MODULES_ONLY = 128;
   // if this bit is set, take modules + content (default = content only)
   const INCLUDE_MODULES = 256;
   
   /**
    * Constructor - Load the JSON
    * 
    * @param string $jsonPath The path to a json file OR the json itself
    * @param string $taskDir The task directory
    * @param boolean $isJson If the first parameter is a JSON string itself
    * @throws Exception if the JSON cannot be read or decoded
    */
   public function __construct($jsonPath, $taskDir, $isJson = false)
   {
      if ($isJson) {
         $this->bebras = json_decode(json_encode($jsonPath));
      }
      else {
         $this->path = $jsonPath;
         $fileContent = file_get_contents($jsonPath);
         if ($fileContent === false) {
            throw new Exception('The JSON '.$jsonPath.' cannot be reached.');
         }
         
         $this->bebras = json_decode($fileContent);
         if ($this->bebras === null) {
            throw new Exception('The JSON '.$jsonPath.' is not recognized as JSON.');
         }
      }

      $this->taskDir = $taskDir;
      $this->taskKey = end((explode('/', $taskDir)));
   }
   
   /**
    * Get the task's title
    * 
    * @return string
    */
   public function getTitle()
   {
      return $this->bebras->title;
   }
   
   /**
    * Get the HTML for importing all javascript and css of the task
    * 
    * @param int $mode Bitmask
    * @return string
    */
   public function getStaticResourcesImportHtml($mode = self::TASK)
   {
      $html = '';
      
      $resources = $this->getResourcesByMode($mode);
      foreach ($resources as $curResource) {
         $htmlResource = $this->getStaticResourceImportHtml($curResource);
         if ($htmlResource) {
            $html .= '         '.$htmlResource."\r\n";
         }
      }
      
      return $html;
   }
   
   /**
    * Get the content of the task
    * 
    * @param int $mode Bitmask
    * @return string
    */
   public function getContent($mode = self::TASK)
   {
      $content = '';
      
      $resources = $this->getResourcesByMode($mode);
      foreach ($resources as $curResource) {
         $content .= $this->getContentResource($curResource);
      }
      
      return $content;
   }
   
   /**
    * Get the javascript of the task
    * 
    * @param int $mode Bitmask
    * @return string
    */
   public function getJavascript($mode = self::TASK)
   {
      $content = '';
      
      $resources = $this->getResourcesByMode($mode);
      foreach ($resources as $curResource) {
         $content .= $this->getContentResource($curResource, 'javascript');
      }
      
      return $content;
   }
   /**
    * Get the css of the task
    * 
    * @param int $mode Bitmask
    * @return string
    */
   public function getCss($mode = self::TASK)
   {
      $content = '';
      
      $resources = $this->getResourcesByMode($mode);
      foreach ($resources as $curResource) {
         $content .= $this->getContentResource($curResource, 'css');
      }
      
      return $content;
   }
   
   /**
    * Get the content resource, type can be html, javascript or css
    * 
    * @param string $resource
    * @return string
    */
   public function getContentResource($resource, $type = 'html')
   {
      $content = '';
      
      if ($resource->type == $type) {
         if (isset($resource->url)) {
            if (strpos($resource->url, 'http://') === 0 || strpos($resource->url, 'https') === 0) {
               $content = file_get_contents($resource->url);
            }
            else {
               $content = file_get_contents($this->taskDir.'/'.$resource->url);
            }
         }
         else {
            $content = $resource->content;
         }
      }
      
      return $content;
   }
   
   /**
    * Get the HTML for importing a resource
    * 
    * @param string $resource
    * @return string
    */
   private function getStaticResourceImportHtml($resource)
   {
      $html = '';
      
      $htmlId = '';
      if (isset($resource->id)) {
          $htmlId = ' id="'.$resource->id.'"';
      }
      
      switch ($resource->type) {
         case 'javascript':
            if (isset($resource->url)) {
               $html = '<script class="'.$resource->meta.'" type="text/javascript" src="'.$resource->url.'"'.$htmlId.'></script>';
            }
            else {
               $html = '<script class="'.$resource->meta.'" type="text/javascript">'.$resource->content.'</script>';
            }
            
            break;
         case 'css':
            if (isset($resource->url)) {
               $html = '<link class="'.$resource->meta.'" rel="stylesheet" type="text/css" href="'.$resource->url.'"'.$htmlId.' />';
            }
            else {
               $html = '<style class="'.$resource->meta.'">'.$resource->content.'</style>';
            }
            
            break;
         default:
            break;
      }
      
      return $html;
   }
   
   /**
    * Get the body parameters to run the task
    * 
    * @return string
    */
   public function getBodyParametersHtml()
   {
      $params = 'onload="load'.str_replace('-', '_', $this->getName()).'()" style="width:770px;border:solid black 1px;margin:0;padding:5px"';
      
      return $params;
   }
   
   /**
    * Retrieve all images of the task
    * 
    * @param $mode Bitmask
    * @return array of string
    */
   public function getImages($mode = self::TASK)
   {
      $images = array();
      
      $resources = $this->getResourcesByMode($mode);
      foreach ($resources as $curResource) {
         if ($curResource->type == 'image') {
            $images[] = $curResource->url;
         }
      }
      
      return $images;
   }
   
   /**
    * Get all the resources filtered by mode
    * 
    * @param int $mode Bitmask
    * @return array of resources
    */
   public function getResourcesByMode($mode)
   {
      $resources = array();
      if ($mode & self::TASK) {
         if (isset($this->bebras->task_modules) && ($mode & self::INCLUDE_MODULES || $mode & self::MODULES_ONLY)) {
            $resources = array_merge($resources, $this->getResourcesWithMeta($this->bebras->task_modules, 'task module'));
         }
         if (!($mode & self::MODULES_ONLY)) {
            $resources = array_merge($resources, $this->getResourcesWithMeta($this->bebras->task, 'task'));
         }
      }
      if ($mode & self::PROXY) {
         if (isset($this->bebras->proxy_modules) && ($mode & self::INCLUDE_MODULES || $mode & self::MODULES_ONLY)) {
            $resources = array_merge($resources, $this->getResourcesWithMeta($this->bebras->proxy_modules, 'proxy module'));
         }
         if (isset($this->bebras->proxy) && !($mode & self::MODULES_ONLY)) {
            $resources = array_merge($resources, $this->getResourcesWithMeta($this->bebras->proxy, 'proxy'));
         }
      }
      if ($mode & self::DISPLAY) {
         if (isset($this->bebras->display_modules) && ($mode & self::INCLUDE_MODULES || $mode & self::MODULES_ONLY)) {
            $resources = array_merge($resources, $this->getResourcesWithMeta($this->bebras->display_modules, 'display module'));
         }
         if (isset($this->bebras->display) && !($mode & self::MODULES_ONLY)) {
            $resources = array_merge($resources, $this->getResourcesWithMeta($this->bebras->display, 'display'));
         }
      }
      if ($mode & self::SAT) {
         if (isset($this->bebras->sat_modules) && ($mode & self::INCLUDE_MODULES || $mode & self::MODULES_ONLY)) {
            $resources = array_merge($resources, $this->getResourcesWithMeta($this->bebras->sat_modules, 'sat module'));
         }
         if (isset($this->bebras->sat) && !($mode & self::MODULES_ONLY)) {
            $resources = array_merge($resources, $this->getResourcesWithMeta($this->bebras->sat, 'sat'));
         }
      }
      if ($mode & self::SOLUTION) {
         if (isset($this->bebras->solution_modules) && ($mode & self::INCLUDE_MODULES || $mode & self::MODULES_ONLY)) {
            $resources = array_merge($resources, $this->getResourcesWithMeta($this->bebras->solution_modules, 'solution module'));
         }
         if (!($mode & self::MODULES_ONLY)) {
            $resources = array_merge($resources, $this->getResourcesWithMeta($this->bebras->solution, 'solution'));
         }
      }
      if ($mode & self::GRADER) {
         if (isset($this->bebras->grader_modules) && ($mode & self::INCLUDE_MODULES || $mode & self::MODULES_ONLY)) {
            $resources = array_merge($resources, $this->getResourcesWithMeta($this->bebras->grader_modules, 'grader module'));
         }
         if (isset($this->bebras->grader) && !($mode & self::MODULES_ONLY)) {
            $resources = array_merge($resources, $this->getResourcesWithMeta($this->bebras->grader, 'grader'));
         }
      }
      
      return $resources;
   }
   
   /**
    * Add metadata to a resource (if it's a module and it's type)
    * 
    * @param object $resources
    * @param string $meta
    * @return array
    */
   public function getResourcesWithMeta($resources, $meta)
   {
      $resourcesWithMeta = array();
      foreach ($resources as $curResources) {
         $resourceWithMeta = $curResources;
         if (!isset($curResources->meta)) {
            $resourceWithMeta->meta = $meta;
         }
         
         $resourcesWithMeta[] = $resourceWithMeta;
      }
      
      return $resourcesWithMeta;
   }
   
   /**
    * Retrieve a javascript coded Array of the accepted answers (eg. ["a", 42, "plop"])
    * 
    * @return {string}
    */
   public function getAcceptedAnswersJavascript()
   {
      $js = '[';
      
      $isFirst = true;
      if (property_exists($this->bebras, 'acceptedAnswers')) {
        foreach ((array) $this->bebras->acceptedAnswers as $curAcceptedAnswer) {
           if ($isFirst) {
              $isFirst = false;
           }
           else {
              $js .= ', ';
           }
           
           $js .= "'".$curAcceptedAnswer."'";
        }
      }
      
      $js .= ']';
      
      return $js;
   }
   
   /**
    * Retrieve the content of bebras.json
    */
   public function getBebrasJson()
   {
      return json_encode($this->bebras);
   }
   
   /**
    * Retrieve the bebras JSON without redundant informations for the question
    */
   public function getBebrasJsonForQuestion()
   {
      $miniBebras = clone $this->bebras;
      unset($miniBebras->task);
      unset($miniBebras->grader);
      unset($miniBebras->solution);
      unset($miniBebras->proxy);
      unset($miniBebras->display);
      unset($miniBebras->sat);
      unset($miniBebras->task_modules);
      unset($miniBebras->grader_modules);
      unset($miniBebras->solution_modules);
      unset($miniBebras->proxy_modules);
      unset($miniBebras->display_modules);
      unset($miniBebras->sat_modules);
      unset($miniBebras->title);
      
      return json_encode($miniBebras, JSON_PRETTY_PRINT);
   }
   
   /**
    * Get the name of the task
    * 
    * @return string
    */
   public function getName()
   {
      return substr(strrchr($this->taskDir, '/'), 1);
   }
   
   /**
    * Converts image relative path to absolute path
    * 
    * @return converted text
    */
   public static function moveQuestionImagesSrc($absolutePath, $text) {
      $images = self::findUsedFiles($text, array('png', 'jpg', 'gif', 'PNG', 'JPG', 'GIF'), true);
      foreach ($images as $image) {
         //$text = str_replace($image, "questions/".$questionData->folder."/".$questionData->key."/".$image, $text);
         $text = str_replace($image, $absolutePath.'/'.$image, $text);
      }
      return $text;
   }

   /**
    * Converts image relative path to absolute path in an array
    * 
    * @return converted array
    */
   public static function addAbsoluteStaticPath($absolutePath, $images) {
      $newImages = array();
      foreach ((array)$images as $image) {
         $newImages[] = $absolutePath.'/'.$image;
      }
      return $newImages;
   }

   /**
    * Parses $html to find urls ending with an extensioncontained in $extensions
    * 
    * @return url array
    */
   public static function findUsedFiles($html, $extensions, $withGenerated = false)
   {
      $images = array();
      $isListed = array();
      foreach ($extensions as $extension) {
         $curPos = 0;
         while (($startImg = strpos($html, $extension, $curPos)) !== false) {
            $endImg = $startImg + 3;
            $delimiter = $html[$endImg];
            if (($delimiter === '\'') || ($delimiter === '"')) {
               while (($startImg > 0) && ($html[$startImg - 1] != $delimiter)) {
                  $startImg--;
               }
               $image = substr($html, $startImg, $endImg - $startImg);
               if ((!isset($isListed[$image])) && ($withGenerated || (strpos($image, '+') === false))) {
                  $isListed[$image] = true;
                  $images[] = $image;
               }
            }
            $curPos = $endImg;
         }
      }
      return $images;
   }
   
   /**
    * Copies task content or solution images into $dstDir/$taskKey
    * 
    * @param int $mode Bitmask with self::CONTENT and self::SOLUTION
    * @param string $copyFuncName is an optional parameter. If present, it must
    *        be the name of a function with the same signature as the "copy"
    *        function, which will be called instead of it.
    *
    * @return array of the images path in the form "$taskKey/imagefile"
    */
   public function copyImages($mode, $dstDir, $copyFuncName = null) {
      $images = array();
      $srcImages = $this->getImages($mode);
      foreach ($srcImages as $curImage) {
         if ($curImage[0] == '/') {
            $curImage = substr($curImage, 1);
         }
         $dstFile = $dstDir.'/'.$curImage;
         if ($copyFuncName) {
            call_user_func($copyFuncName, $this->taskDir.'/'.$curImage, $dstFile);
         } else {
            copy($this->taskDir.'/'.$curImage, $dstFile);
         }
         $images[] = $curImage;
      }
      
      return $images;
   }

   /**
    * gets array of css and js modules, inline and as reference
    * 
    * @return url array
    */
   public function getModules($blacklist = array()) {
      // Javascript & css modules
      $res = array(
         'jsModules' => array('ref' => array(), 'inline' => array()),
         'cssModules' => array('ref' => array(), 'inline' => array()),
      );
      foreach ($this->getResourcesByMode(self::TASK | self::PROXY | self::DISPLAY | self::SAT | self::SOLUTION | self::GRADER | self::MODULES_ONLY) as $curResource) {
         if ($curResource->type == 'javascript') {
            if (isset($curResource->content)) {
               $res['jsModules']['inline'][] = $curResource->content;
            }
            else {
               foreach($blacklist as $moduleUrl) {
                  if ($curResource->url == $moduleUrl) {
                     continue;
                  }
               }
               $res['jsModules']['ref'][preg_replace('#[^a-zA-z0-9]#', '', $curResource->id)] = $this->getContentResource($curResource, 'javascript');
            }
         }
         else if ($curResource->type == 'css') {
            if (isset($curResource->content)) {
               $res['cssModules']['inline'][] = $curResource->content;
            }
            else {
               $res['cssModules']['ref'][preg_replace('#[^a-zA-z0-9]#', '', $curResource->id)] = $this->getContentResource($curResource, 'css');
            }
         }
      }
      
      return $res;
   }

   /**
    * Gets grader
    * 
    * @return grader
    */
   public function getGrader() {
      $jsGrader = "";
      if (property_exists($this->bebras, 'fullFeedback') && $this->bebras->fullFeedback == true) {
         return $jsGrader;
      }
      foreach ($this->getResourcesByMode(self::GRADER) as $curResource) {
         if (isset($curResource->content)) {
            $jsGrader .= $curResource->content;
         }
         else {
            $jsGrader .= $this->getContentResource($curResource, 'javascript');
         }
      }
      if ( ! $jsGrader) {
         $jsGrader = 
            'if (typeof grader === "undefined" && typeof task.gradeAnswer === "undefined") {'
           .'   window.grader = {'."\n"
           .'      gradeTask: function(answer, answerToken, callback) {'."\n"
           .'         platform.getTaskParams(function(taskParams) {'."\n"
           ."            if ($.inArray(answer+'', ".$this->getAcceptedAnswersJavascript().") > -1) {\n"
           .'               score = taskParams.maxScore;'."\n"
           .'            } else {'."\n"
           .'               score = taskParams.minScore;'."\n"
           .'            }'."\n"
           ."            callback(score, '');\n"
           ."         });\n"
           .'      }'."\n"
           .'   }'."\n"
           .'}'."\n";
      }
      return $jsGrader;
   }

   /**
    * Generates task in $dstDir, with absolute url $absolutePath.
    *
    * If proxyJS != null (might be ""), uses it instead of the proxy lib used by task.
    * (same goes for displayJS and satJS).
    * 
    * @returns success
    */
   public function generate($dstDir, $absolutePath, $proxyJS = null, $displayJS = null, $satJS = null) {
      $jsQuestions = '';
      $cssQuestions = '';
      $images = $this->copyImages(self::TASK | self::INLINE_ONLY, $dstDir);
      $imagesSols = $this->copyImages(self::SOLUTION, $dstDir);
      
      // Javascript & css modules
      $questionRelatedJs = self::moveQuestionImagesSrc($absolutePath, $this->getJavascript(self::TASK));
      $solutionRelatedJs = self::moveQuestionImagesSrc($absolutePath, $this->getJavascript(self::SOLUTION));
      $cssQuestions = self::moveQuestionImagesSrc($absolutePath, $this->getCss(self::TASK));
      $cssSolutions = self::moveQuestionImagesSrc($absolutePath, $this->getCss(self::SOLUTION));
      $modules = $this->getModules();
      $jsCurrentModules = $modules['jsModules']['ref'];
      $cssCurrentModules = $modules['cssModules']['ref'];
      // JS modules content
      foreach ($modules['jsModules']['inline'] as $curJsModuleContent) {
         $jsQuestions .= $curJsModuleContent;
      }
      // Css modules content
      foreach ($modules['cssModules']['inline'] as $curCssModuleContent) {
         $cssQuestions .= $curCssModuleContent;
      }
      $jsGrader = $this->getGrader();
      $proxyJS = ($proxyJS === null) ? $this->getJavascript(self::PROXY) : $proxyJS;
      $displayJS = ($displayJS === null) ? $this->getJavascript(self::DISPLAY) : $displayJS;
      $satJS = ($satJS === null) ? $this->getJavascript(self::SAT) : $satJS;
      
      // Content
      $questionBody = $this->getContent(self::TASK);
      
      // Remove absolute images
      $questionBody = preg_replace('#http\://.*\.(png|jpg|gif|jpeg)#isU', '', $questionBody);
      $strQuestion = '<div id="question-'.$this->taskKey.'" class="question"><div id="task" class="taskView">'."\n"
              .'<style>'.$cssQuestions.'</style>'
              .self::moveQuestionImagesSrc($absolutePath, $questionBody)
              .'</div></div>'."\n";
      foreach ($jsCurrentModules as $name => $content) {
         $strQuestion .= '<script type="text/javascript">'.$content.'</script>'."\n";
      }
      if ($proxyJS || $displayJS || $satJS) {
         $strQuestion .= "\n".'<script type="text/javascript">'.$proxyJS.$displayJS.$satJS.'</script>'."\n";
      }
      $strQuestion .= "\n".'<script type="text/javascript">'.$jsQuestions.$questionRelatedJs.'</script>'."\n";
      foreach ($cssCurrentModules as $name => $content) {
         $strQuestion .= '<style type="text/css">'.$content.'</style>'."\n";
      }
      $strQuestion .= file_get_contents(__DIR__.'/common.inc.js');
      
      $questionSolution = $this->getContent(self::SOLUTION);
      $strSolution = '<div id="solution-'.$this->taskKey.'" class="solution"><div id="solution" class="taskView">'."\n"
              .'<style>'.$cssSolutions.'</style>'
              .self::moveQuestionImagesSrc($absolutePath, $questionSolution)
              .'</div></div>'."\n"
              .'<script type="text/javascript">'.htmlspecialchars($solutionRelatedJs, ENT_COMPAT, 'UTF-8').'</script>'."\n";
      

       $bebrasJsContent = 'var json = '.$this->getBebrasJson().'; function getTaskResources() { return json; }';
       $htAccessContent =
          '<Files "index.html">'."\n"
            ."\t".'Deny from all'."\n"
         .'</Files>'."\n"
         .'<Files "grader.js">'."\n"
            ."\t".'Deny from all'."\n"
         .'</Files>'."\n"
         .'<Files "solution.html">'."\n"
            ."\t".'Deny from all'."\n"
         .'</Files>'."\n"
         .'<Files "bebras.js">'."\n"
            ."\t".'Deny from all'."\n"
         .'</Files>'."\n";
       // Create files
      file_put_contents($dstDir.'/bebras.js', $bebrasJsContent);
      file_put_contents($dstDir.'/solution.html', $strSolution);
      file_put_contents($dstDir.'/grader.js', $jsGrader);
      file_put_contents($dstDir.'/.htaccess', $htAccessContent);
      file_put_contents($dstDir.'/index.html', $strQuestion);
      return true;
   }
}
