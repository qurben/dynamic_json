<?php

  require_once(EXTENSIONS . '/dynamic_json/data-sources/datasource.dynamic_json.php');

  Class datasource<!-- CLASS NAME --> extends DynamicJSONDatasource{

    <!-- VAR LIST -->

    public function __construct($env=NULL, $process_params=true){
      parent::__construct($env, $process_params);
      $this->_dependencies = array(<!-- DS DEPENDENCY LIST -->);
    }

    public function about(){
      return array(
        'name' => '<!-- NAME -->',
        'author' => array(
          'name' => '<!-- AUTHOR NAME -->',
          'website' => '<!-- AUTHOR WEBSITE -->',
          'email' => '<!-- AUTHOR EMAIL -->'),
        'version' => 'Dynamic JSON Datasource <!-- VERSION -->',
        'release-date' => '<!-- RELEASE DATE -->'
      );
    }

    public function allowEditorToParse(){
      return true;
    }

  }
