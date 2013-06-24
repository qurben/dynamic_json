<?php

  REQUIRE_ONCE 'data-sources/datasource.dynamic_json.php';
  
  class Extension_Dynamic_JSON extends Extension {

    private static $provides = array();
    public function about() {
      return array(
        'name'          => 'Dynamic JSON',
        'version'       => '0.1.0',
        'release-date'  => '2013-06-24',
        'author'        => array(
          array(
            'name'    => 'Gerben Oolbekkink',
            'website' => 'http://qurben.com',
            'email'   => 'qurben@gmail.com'
          )
        )
      );
    }

    public static function registerProviders() {
      self::$provides = array(
        'data-sources' => array(
          'DynamicJSONDatasource' => DynamicJSONDatasource::getName()
        )
      );

      return true;
    }

    public static function providerOf($type = null) {
      self::registerProviders();

      if(is_null($type)) return self::$provides;

      if(!isset(self::$provides[$type])) return array();

      return self::$provides[$type];
    }
  }