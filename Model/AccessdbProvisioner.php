<?php

class AccessdbProvisioner extends AppModel {
  // Required by COmanage Plugins
  public $cmPluginType = "provisioner";

  // Document foreign keys
  public $cmPluginHasMany = array();
  
  /**
   * Expose menu items.
   * 
   * @ since COmanage Registry v4.1.0
   * @ return Array with menu location type as key and array of labels, controllers, actions as values.
   */
  
  public function cmPluginMenus() {
    return array();
  }
}
