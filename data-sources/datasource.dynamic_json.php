<?php

  require_once TOOLKIT . '/class.datasource.php';
  require_once TOOLKIT . '/class.json.php';
  require_once FACE . '/interface.datasource.php';

  Class DynamicJSONDatasource extends Datasource implements iDatasource {

    /**
     * An array of Field objects, used to stop unnecessary creation of field objects
     * @var array
     */
    public static $field_pool = array();

    public static $system_parameters = array(
      'system:id',
      'system:author',
      'system:date'
    );

    public static function getName() {
      return __('Dynamic JSON');
    }

    public static function getClass() {
      return __CLASS__;
    }

    public function getSource() {
      return self::getClass();
    }

    public static function getTemplate(){
      return EXTENSIONS . '/dynamic_json/templates/blueprints.datasource.tpl';
    }

    public function settings() {
      $settings = array();
      $class = self::getClass();

      $settings[$class] = array(
        'url' => $this->dsParamURL,
        'cache' => $this->dsParamCACHE,
        'xpath' => $this->dsParamXPATH,
        'timeout' => $this->dsParamTIMEOUT,
        'format' => $this->dsParamFORMAT,
        'namespace' => $this->dsParamFILTERS
      );

      return $settings;
    }

  /*-------------------------------------------------------------------------
    Utilities
  -------------------------------------------------------------------------*/

    /**
     * Returns the source value for display in the Datasources index
     */
    public function getSourceColumn() {
        return self::getName();
    }

  /*-------------------------------------------------------------------------
    Editor
  -------------------------------------------------------------------------*/

    /**
     * Add options to the editor
     * @param $wrapper
     * The options page
     * @param $errors
     * Array to write errors to
     * @param $settings
     * Array with all the settings
     *
     */
    public static function buildEditor(XMLElement $wrapper, array &$errors = array(), array $settings = null, $handle = null) {
      $class = self::getClass();

      if (!isset($settings[$class])) {
        $settings[$class]['url'] = '';
        $settings[$class]['cache'] = '30';
        $settings[$class]['xpath'] = '/';
        $settings[$class]['timeout'] = '6';
        $settings[$class]['format'] = 'XML';
      }

      $fieldset = new XMLElement('fieldset');
      $fieldset->setAttribute('class', 'settings contextual ' . $class);
      $fieldset->appendChild(new XMLElement('legend', __('Dynamic JSON')));

      $label = Widget::Label(__('URL'));
      $label->appendChild(Widget::Input("fields[$class][url]", General::sanitize($settings[$class]['url'])));
      if(isset($errors[$class]['url'])) $fieldset->appendChild(Widget::Error($label, $errors[$class]['url']));
      else $fieldset->appendChild($label);

      $p = new XMLElement('p',
        __('Use %s syntax to specify dynamic portions of the URL.', array(
          '<code>{' . __('$param') . '}</code>'
        ))
      );
      $p->setAttribute('class', 'help');
      $label->appendChild($p);

      $div = new XMLElement('div');
      $p = new XMLElement('p', __('Namespace Declarations'));
      $p->appendChild(new XMLElement('i', __('Optional')));
      $p->setAttribute('class', 'label');
      $div->appendChild($p);

      $ol = new XMLElement('ol');
      $ol->setAttribute('class', 'filters-duplicator');
      $ol->setAttribute('data-add', __('Add namespace'));
      $ol->setAttribute('data-remove', __('Remove namespace'));

      if(is_array($settings[$class]['namespace'])){
        $i = 0;
        foreach($settings[$class]['namespace'] as $name => $uri){
          // Namespaces get saved to the file as $name => $uri, however in
          // the $_POST they are represented as $index => array. This loop
          // patches the difference.
          if(is_array($uri)) {
            $name = $uri['name'];
            $uri = $uri['uri'];
          }

          $li = new XMLElement('li');
          $li->appendChild(new XMLElement('header', '<h4>' . __('Namespace') . '</h4>'));

          $group = new XMLElement('div');
          $group->setAttribute('class', 'group');

          $label = Widget::Label(__('Name'));
          $label->appendChild(Widget::Input("fields[$class][namespace][$i][name]", General::sanitize($name)));
          $group->appendChild($label);

          $label = Widget::Label(__('URI'));
          $label->appendChild(Widget::Input("fields[$class][namespace][$i][uri]", General::sanitize($uri)));
          $group->appendChild($label);

          $li->appendChild($group);
          $ol->appendChild($li);
          $i++;
        }
      }

      $li = new XMLElement('li');
      $li->setAttribute('class', 'template');
      $li->setAttribute('data-type', 'namespace');
      $li->appendChild(new XMLElement('header', '<h4>' . __('Namespace') . '</h4>'));

      $group = new XMLElement('div');
      $group->setAttribute('class', 'group');

      $label = Widget::Label(__('Name'));
      $label->appendChild(Widget::Input("fields[$class][namespace][-1][name]"));
      $group->appendChild($label);

      $label = Widget::Label(__('URI'));
      $label->appendChild(Widget::Input("fields[$class][namespace][-1][uri]"));
      $group->appendChild($label);

      $li->appendChild($group);
      $ol->appendChild($li);

      $div->appendChild($ol);
      $fieldset->appendChild($div);

      $label = Widget::Label(__('Included Elements'));
      $label->appendChild(Widget::Input("fields[$class][xpath]", General::sanitize($settings[$class]['xpath'])));
      if(isset($errors[$class]['xpath'])) $fieldset->appendChild(Widget::Error($label, $errors[$class]['xpath']));
      else $fieldset->appendChild($label);

      $p = new XMLElement('p', __('Use an XPath expression to select which elements from the source XML to include.'));
      $p->setAttribute('class', 'help');
      $fieldset->appendChild($p);

      $label = Widget::Label();
      $input = Widget::Input("fields[$class][cache]", (string)max(1, intval($settings[$class]['cache'])), 'text', array('size' => '6'));
      $label->setValue(__('Update cached result every %s minutes', array($input->generate(false))));
      if(isset($errors[$class]['cache'])) $fieldset->appendChild(Widget::Error($label, $errors[$class]['cache']));
      else $fieldset->appendChild($label);

      $label = Widget::Label();
      $input = Widget::Input("fields[$class][timeout]", (string)max(1, intval($settings[$class]['timeout'])), 'text', array('type' => 'hidden'));
      $label->appendChild($input);
      $fieldset->appendChild($label);

      $wrapper->appendChild($fieldset);
    }

    public static function validate(array &$fields, array &$errors) {
      $class = self::getClass();

      if(trim($fields[$class]['url']) == '') $errors[$class]['url'] = __('This is a required field');

      // Use the TIMEOUT that was specified by the user for a real world indication
      $timeout = (isset($fields[$class]['timeout']) ? (int)$fields[$class]['timeout'] : 6);

      // If there is a parameter in the URL, we can't validate the existence of the URL
      // as we don't have the environment details of where this datasource is going
      // to be executed.
      if(!preg_match('@{([^}]+)}@i', $fields[$class]['url'])) {
        $valid_url = self::__isValidURL($fields[$class]['url'], $timeout, $error);

        if($valid_url) {
          $data = $valid_url['data'];
        }
        else {
          $errors[$class]['url'] = $error;
        }
      }

      $fields[$class]['data'] = $data;

      if(trim($fields[$class]['xpath']) == '') $errors[$class]['xpath'] = __('This is a required field');

      if(!is_numeric($fields[$class]['cache'])) $errors[$class]['cache'] = __('Must be a valid number');
      elseif($fields[$class]['cache'] < 1) $errors[$class]['cache'] = __('Must be greater than zero');

      return empty($errors[$class]);
    }

    public static function prepare(array $settings, array $params, $template) {
      $class = self::getClass();

      $params['url'] = $settings[$class]['url'];
      $params['xpath'] = $settings[$class]['xpath'];
      $params['cache'] = $settings[$class]['cache'];
      $params['format'] = $settings[$class]['format'];
      $params['timeout'] = (isset($settings[$class]['timeout']) ? (int)$settings[$class]['timeout'] : '6');

      self::__injectVarList($params,$template);

      $filters = array();
      if(is_array($settings[$class]['namespace'])) foreach($settings[$class]['namespace'] as $index => $data) {
        $filters[$data['name']] = $data['uri'];
      }

      self::__injectFilters($filters,$template);

      $extends = 'DynamicJSONDatasource';

      return $template;
    }

  /*-------------------------------------------------------------------------
    Execution
  -------------------------------------------------------------------------*/

    public function execute(array &$param_pool = null) {

      $result = new XMLElement($this->dsParamROOTELEMENT);

      $this->dsParamURL = $this->parseParamURL($this->dsParamURL);

      if(isset($this->dsParamXPATH)) $this->dsParamXPATH = $this->__processParametersInString($this->dsParamXPATH, $this->_env);

      $stylesheet = new XMLElement('xsl:stylesheet');
      $stylesheet->setAttributeArray(array('version' => '1.0', 'xmlns:xsl' => 'http://www.w3.org/1999/XSL/Transform'));

      $output = new XMLElement('xsl:output');
      $output->setAttributeArray(array('method' => 'xml', 'version' => '1.0', 'encoding' => 'utf-8', 'indent' => 'yes', 'omit-xml-declaration' => 'yes'));
      $stylesheet->appendChild($output);

      $template = new XMLElement('xsl:template');
      $template->setAttribute('match', '/');

      $instruction = new XMLElement('xsl:copy-of');

      // Namespaces
      if(isset($this->dsParamFILTERS) && is_array($this->dsParamFILTERS)){
        foreach($this->dsParamFILTERS as $name => $uri) $instruction->setAttribute('xmlns' . ($name ? ":{$name}" : NULL), $uri);
      }

      // XPath
      $instruction->setAttribute('select', $this->dsParamXPATH);

      $template->appendChild($instruction);
      $stylesheet->appendChild($template);

      $stylesheet->setIncludeHeader(true);

      $xsl = $stylesheet->generate(true);

      $cache_id = md5($this->dsParamURL . serialize($this->dsParamFILTERS) . $this->dsParamXPATH);

      $cache = new Cacheable(Symphony::Database());

      $cachedData = $cache->check($cache_id);
      $writeToCache = false;
      $valid = true;
      $creation = DateTimeObj::get('c');
      $timeout = (isset($this->dsParamTIMEOUT)) ? (int)max(1, $this->dsParamTIMEOUT) : 6;

      // Execute if the cache doesn't exist, or if it is old.
      if(
        (!is_array($cachedData) || empty($cachedData)) // There's no cache.
        || (time() - $cachedData['creation']) > ($this->dsParamCACHE * 60) // The cache is old.
      ){
        if(Mutex::acquire($cache_id, $timeout, TMP)) {
          $ch = new Gateway;
          $ch->init($this->dsParamURL);
          $ch->setopt('TIMEOUT', $timeout);
          $ch->setopt('HTTPHEADER', array('Accept: text/json, */*'));

          $data = $ch->exec();
          $info = $ch->getInfoLast();

          Mutex::release($cache_id, TMP);

          $data = trim($data);
          $writeToCache = true;

          // Handle any response that is not a 200, or the content type does not include JSON, plain or text
          if((int)$info['http_code'] != 200 || !preg_match('/(json|plain|text)/i', $info['content_type'])){
            $writeToCache = false;

            $result->setAttribute('valid', 'false');

            // 28 is CURLE_OPERATION_TIMEOUTED
            if($info['curl_error'] == 28) {
              $result->appendChild(
                new XMLElement('error',
                  sprintf('Request timed out. %d second limit reached.', $timeout)
                )
              );
            }
            else{
              $result->appendChild(
                new XMLElement('error',
                  sprintf('Status code %d was returned. Content-type: %s', $info['http_code'], $info['content_type'])
                )
              );
            }

            return $result;
          }

          // Handle where there is `$data`
          else if(strlen($data) > 0) {
            // If the XML doesn't validate..

            try {
              // Convert the received data immediatly to XML
              $data = JSON::convertToXML($data,false);
            } catch(JSONException $e) {
              $writeToCache = false;
              $element = new XMLElement('errors');

              $result->setAttribute('valid', 'false');

              $result->appendChild(new XMLElement('error', __('Data returned is invalid.')));

              $element->appendChild(new XMLElement('item', $e->getMessage()));

              $result->appendChild($element);

              return $result;
            }
          }
          // If `$data` is empty, set the `force_empty_result` to true.
          else if(strlen($data) == 0){
            $this->_force_empty_result = true;
          }
        }

        // Failed to acquire a lock
        else {
          $result->appendChild(
            new XMLElement('error', __('The %s class failed to acquire a lock, check that %s exists and is writable.', array(
              '<code>Mutex</code>',
              '<code>' . TMP . '</code>'
            )))
          );
        }
      }

      // The cache is good, use it!
      else {
        $data = trim($cachedData['data']);
        $creation = DateTimeObj::get('c', $cachedData['creation']);
      }

      // If `$writeToCache` is set to false, invalidate the old cache if it existed.
      if(is_array($cachedData) && !empty($cachedData) && $writeToCache === false) {
        $data = trim($cachedData['data']);
        $valid = false;
        $creation = DateTimeObj::get('c', $cachedData['creation']);

        if(empty($data)) $this->_force_empty_result = true;
      }

      // If `force_empty_result` is false and `$result` is an instance of
      // XMLElement, build the `$result`.
      if(is_object($result)) {
        $proc = new XsltProcess; 
        $ret = $proc->process($data, $xsl);

        if($proc->isErrors()) {
          $result->setAttribute('valid', 'false');
          $error = new XMLElement('error', __('Transformed XML is invalid.'));
          $result->appendChild($error);
          $element = new XMLElement('errors');
          foreach($proc->getError() as $e) {
            if(strlen(trim($e['message'])) == 0) continue;
            $element->appendChild(new XMLElement('item', General::sanitize($e['message'])));
          }
          $result->appendChild($element);
        }

        else if(strlen(trim($ret)) == 0) {
          $this->_force_empty_result = true;
        }

        else {
          if($writeToCache) $cache->write($cache_id, $data, $this->dsParamCACHE);

          $result->setValue(PHP_EOL . str_repeat("\t", 2) . preg_replace('/([\r\n]+)/', "$1\t", $ret));
          $result->setAttribute('status', ($valid === true ? 'fresh' : 'stale'));
          $result->setAttribute('creation', $creation);
        }

      }

      return $result;
    }
    
  /*-------------------------------------------------------------------------
    Legacy functions for Datasources that don't inherit SectionsDatasource
  -------------------------------------------------------------------------*/

    public function processFilters(Datasource $datasource, &$where, &$joins, &$group) {
      if(!is_array($datasource->dsParamFILTERS) || empty($datasource->dsParamFILTERS)) return;

      $pool = FieldManager::fetch(array_filter(array_keys($datasource->dsParamFILTERS), 'is_int'));
      self::$field_pool += $pool;

      foreach($datasource->dsParamFILTERS as $field_id => $filter){

        if((is_array($filter) && empty($filter)) || trim($filter) == '') continue;

        if(!is_array($filter)){
          $filter_type = $datasource->__determineFilterType($filter);

          $value = preg_split('/'.($filter_type == DS_FILTER_AND ? '\+' : '(?<!\\\\),').'\s*/', $filter, -1, PREG_SPLIT_NO_EMPTY);
          $value = array_map('trim', $value);
          $value = array_map(array('Datasource', 'removeEscapedCommas'), $value);
        }

        else $value = $filter;

        if($field_id != 'id' && $field_id != 'system:date' && !(self::$field_pool[$field_id] instanceof Field)){
          throw new Exception(
            __(
              'Error creating field object with id %1$d, for filtering in data source %2$s. Check this field exists.',
              array($field_id, '<code>' . $this->dsParamROOTELEMENT . '</code>')
            )
          );
        }

        if($field_id == 'id') {
          $c = 'IN';
          if(stripos($value[0], 'not:') === 0) {
            $value[0] = preg_replace('/^not:\s*/', null, $value[0]);
            $c = 'NOT IN';
          }

          // Cast all ID's to integers.
          $value = array_map(create_function('$x', 'return (int)$x;'),$value);
          $value = array_filter($value);

          // If there are no ID's, no need to filter. RE: #1567
          if(!empty($value)) {
            $where .= " AND `e`.id " . $c . " (".implode(", ", $value).") ";
          }
        }
        else if($field_id == 'system:date') {
          require_once(TOOLKIT . '/fields/field.date.php');
          $date_joins = '';
          $date_where = '';
          $date = new fieldDate();
          $date->buildDSRetrievalSQL($value, $date_joins, $date_where, ($filter_type == DS_FILTER_AND ? true : false));

          // Replace the date field where with the `creation_date` or `modification_date`.
          $date_where = preg_replace('/`t\d+`.date/', ($field_id !== 'system:modification-date') ? '`e`.creation_date_gmt' : '`e`.modification_date_gmt', $date_where);
          $where .= $date_where;
        }
        else {
          // For deprecated reasons, call the old, typo'd function name until the switch to the
          // properly named buildDSRetrievalSQL function.
          if(!self::$field_pool[$field_id]->buildDSRetrivalSQL($value, $joins, $where, ($filter_type == DS_FILTER_AND ? true : false))){ $datasource->_force_empty_result = true; return; }
          if(!$group) $group = self::$field_pool[$field_id]->requiresSQLGrouping();
        }
      }
    }

    public static function __injectVarList($vars, &$shell){
      if(!is_array($vars) || empty($vars)) return;

      $var_list = NULL;
      foreach($vars as $key => $val){
        if(is_array($val)) {
          $val = "array(" . PHP_EOL . "\t\t\t\t'" . implode("'," . PHP_EOL . "\t\t\t\t'", $val) . "'" . PHP_EOL . '   );';
          $var_list .= '    public $dsParam' . strtoupper($key) . ' = ' . $val . PHP_EOL;
        }
        else if(trim($val) !== '') {
          $var_list .= '    public $dsParam' . strtoupper($key) . " = '" . addslashes($val) . "';" . PHP_EOL;
        }
      }

      $placeholder = '<!-- VAR LIST -->';
      $shell = str_replace($placeholder, trim($var_list) . PHP_EOL . "\t\t" . $placeholder, $shell);
    }

    public static function __injectFilters(array $filters, &$shell){
      if(empty($filters)) return;

      $placeholder = '<!-- FILTERS -->';
      $string = 'public $dsParamFILTERS = array(' . PHP_EOL;

      foreach($filters as $key => $val){
        if(trim($val) == '') continue;
        $string .= "\t\t\t\t'$key' => '" . addslashes($val) . "'," . PHP_EOL;
      }

      $string .= "\t\t);" . PHP_EOL . "\t\t" . $placeholder;

      $shell = str_replace($placeholder, trim($string), $shell);
    }

    /**
     * Given a `$url` and `$timeout`, this function will use the `Gateway`
     * class to determine that it is a valid URL and returns successfully
     * before the `$timeout`. If it does not, an error message will be
     * returned, otherwise true.
     *
     * @since Symphony 2.3
     * @param string $url
     * @param integer $timeout
     *  If not provided, this will default to 6 seconds
     * @param string $error
     *  If this function returns false, this variable will be populated with the
     *  error message.
     * @return array|boolean
     *  Returns an array with the 'data' if it is a valid URL, otherwise a string
     *  containing an error message.
     */
    public static function __isValidURL($url, $timeout = 6, &$error) {
      if(!filter_var($url, FILTER_VALIDATE_URL)) {
        $error = __('Invalid URL');
        return false;
      }

      // Check that URL was provided
      $gateway = new Gateway;
      $gateway->init($url);
      $gateway->setopt('TIMEOUT', $timeout);
      $data = $gateway->exec();

      $info = $gateway->getInfoLast();

      // 28 is CURLE_OPERATION_TIMEOUTED
      if($info['curl_error'] == 28) {
        $error = __('Request timed out. %d second limit reached.', array($timeout));
        return false;
      }
      else if($data === false || $info['http_code'] != 200) {
        $error = __('Failed to load URL, status code %d was returned.', array($info['http_code']));
        return false;
      }

      return array('data' => $data);
    }
  }

  return 'DynamicJSONDatasource';
