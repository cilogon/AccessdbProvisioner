<?php

App::uses("SPTController", "Controller");

class CoAccessdbProvisionerTargetsController extends SPTController {
  // Class name, used by Cake
  public $name = "CoAccessdbProvisionerTargets";
  
  // Establish pagination parameters for HTML views
  public $paginate = array(
    'limit' => 25,
    'order' => array(
      'server_id' => 'asc'
    )
  );
  
  /**
   * Callback after controller methods are invoked but before views are rendered.
   *
   * @since  COmanage Registry v4.1.0
   */

  function beforeRender() {
    parent::beforeRender();
    
    if(!$this->request->is('restful')) {
      $this->set('vv_identifiers_types', $this->CoAccessdbProvisionerTarget->CoProvisioningTarget->Co->CoPerson->Identifier->types($this->cur_co['Co']['id'], 'type'));
    }
  }
  
  /**
   * Authorization for this Controller, called by Auth component
   * - precondition: Session.Auth holds data used for authz decisions
   * - postcondition: $permissions set with calculated permissions
   *
   * @since  COmanage Registry v4.1.0
   * @return Array Permissions
   */
  
  function isAuthorized() {
    $roles = $this->Role->calculateCMRoles();
    
    // Construct the permission set for this user, which will also be passed to the view.
    $p = array();
    
    // Determine what operations this user can perform
    
    // Delete an existing CO Provisioning Target?
    $p['delete'] = ($roles['cmadmin'] || $roles['coadmin']);
    
    // Edit an existing CO Provisioning Target?
    $p['edit'] = ($roles['cmadmin'] || $roles['coadmin']);
    
    // View all existing CO Provisioning Targets?
    $p['index'] = ($roles['cmadmin'] || $roles['coadmin']);
    
    // View an existing CO Provisioning Target?
    $p['view'] = ($roles['cmadmin'] || $roles['coadmin']);
    
    $this->set('permissions', $p);
    return($p[$this->action]);
  }
}
