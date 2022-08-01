<?php

App::uses("CoProvisionerPluginTarget", "Model");
App::uses("AccessOrganization", "AccessOrganization.Model");

class CoAccessdbProvisionerTarget extends CoProvisionerPluginTarget {
  // Define class name for cake
  public $name = "CoAccessdbProvisionerTarget";
  
  // Add behaviors
  public $actsAs = array('Containable');
  
  // Association rules from this model to other models
  public $belongsTo = array(
    "CoProvisioningTarget",
    "Server"
  );
  
  // Default display field for cake generated views
  public $displayField = "server_id";
  
  // Request HTTP servers
  public $cmServerType = ServerEnum::HttpServer;
  
  protected $Http = null;
  
  // Validation rules for table elements
  public $validate = array(
    'co_provisioning_target_id' => array(
      'rule' => 'numeric',
      'required' => true,
      'allowEmpty' => false
    ),
    'server_id' => array(
      'content' => array(
        'rule' => 'numeric',
        'required' => true,
        'allowEmpty' => false,
        'unfreeze' => 'CO'
      )
    ),
    'identifier_type' => array(
      'content' => array(
        'rule' => array('validateExtendedType',
                        array('attribute' => 'Identifier.type',
                              'default' => array(IdentifierEnum::ePPN,
                                                 IdentifierEnum::ePTID,
                                                 IdentifierEnum::Mail,
                                                 IdentifierEnum::OIDCsub,
                                                 IdentifierEnum::OpenID,
                                                 IdentifierEnum::SamlPairwise,
                                                 IdentifierEnum::SamlSubject,
                                                 IdentifierEnum::UID))),
        'required' => true,
        'allowEmpty' => false
      )
    )
  );
  
  /**
   * Provision for the specified CO Person.
   *
   * @since  COmanage Registry v4.1.0
   * @param  Array CO Provisioning Target data
   * @param  ProvisioningActionEnum Registry transaction type triggering provisioning
   * @param  Array Provisioning data, populated with ['CoPerson'] or ['CoGroup']
   * @return Boolean True on success
   * @throws RuntimeException
   */
  
  public function provision($coProvisioningTargetData, $op, $provisioningData) {
    // First determine what to do

    // We don't do anything for CoGroup actions.

    $deletePerson = false;
    $syncPerson = false;

    switch($op) {
      case ProvisioningActionEnum::CoGroupAdded:
      case ProvisioningActionEnum::CoGroupUpdated:
      case ProvisioningActionEnum::CoGroupReprovisionRequested:
      case ProvisioningActionEnum::CoGroupDeleted:
        break;
      case ProvisioningActionEnum::CoPersonAdded:
      case ProvisioningActionEnum::CoPersonEnteredGracePeriod:
      case ProvisioningActionEnum::CoPersonExpired:
      case ProvisioningActionEnum::CoPersonPetitionProvisioned:
      case ProvisioningActionEnum::CoPersonPipelineProvisioned:
      case ProvisioningActionEnum::CoPersonReprovisionRequested:
      case ProvisioningActionEnum::CoPersonUnexpired:
      case ProvisioningActionEnum::CoPersonUpdated:
        if($provisioningData['CoPerson']['status'] == StatusEnum::Deleted) {
          $deletePerson = true;
        } else {
          $syncPerson = true;
        }
        break;
      case ProvisioningActionEnum::CoPersonDeleted:
        $deletePerson = true;
        break;
      default:
        // Ignore all other actions. Note group membership changes
        // are typically handled as CoPersonUpdated events.
        return true;
        break;
    }
    
    if($deletePerson || $syncPerson) {
      // Pull the Server configuation
      
      $args = array();
      $args['conditions']['Server.id'] = $coProvisioningTargetData['CoAccessdbProvisionerTarget']['server_id'];
      $args['conditions']['Server.status'] = SuspendableStatusEnum::Active;
      $args['contain'] = array('HttpServer');

      $CoProvisioningTarget = new CoProvisioningTarget();
      $srvr = $CoProvisioningTarget->Co->Server->find('first', $args);
      
      if(empty($srvr)) {
        throw new InvalidArgumentException(_txt('er.notfound', array(_txt('ct.http_servers.1'), $coProvisioningTargetData['CoAccessdbProvisionerTarget']['server_id'])));
      }
      
      $this->Http = new CoHttpClient(array(
        'ssl_verify_peer' => $srvr['HttpServer']['ssl_verify_peer'],
        'ssl_verify_host' => $srvr['HttpServer']['ssl_verify_host']
      ));
      
      $this->Http->setBaseUrl($srvr['HttpServer']['serverurl']);
      $this->Http->setRequestOptions(array(
        'header' => array(
          'Accept'        => 'application/json',
          'Content-Type'  => 'application/json',
          'XA-REQUESTER'  => $srvr['HttpServer']['username'],
          'XA-API-KEY'    => $srvr['HttpServer']['password']
        )
      ));
    }
    
    if($deletePerson) {
      $this->deletePerson($coProvisioningTargetData['CoAccessdbProvisionerTarget'],
                          $provisioningData['CoPerson']['id']);
    }

    if($syncPerson) {
      $this->syncPerson($coProvisioningTargetData['CoAccessdbProvisionerTarget'],
                        $provisioningData);
    }
  }
  
  /**
   * Delete a CO Person.
   * 
   * @since  COmanage Registry v4.1.0
   * @param  array            $coAccessdbProvisionerTarget CoAccessdbProvisioningTarget
   * @param  Integer          $coPersonId             CoPerson ID
   * @return boolean          true
   * @throws RuntimeException
   */
  
  protected function deletePerson($coAccessdbProvisionerTarget,
                                  $coPersonId) {
    // Currently delete is a no-op.
    return true;
  }

 /**
   * Determine the provisioning status of this target.
   *
   * @since  COmanage Registry v4.1.0
   * @param  Integer $coProvisioningTargetId CO Provisioning Target ID
   * @param  Model   $Model                  Model being queried for status (eg: CoPerson, CoGroup,
   *                                         CoEmailList, COService)
   * @param  Integer $id                     $Model ID to check status for
   * @return Array ProvisioningStatusEnum, Timestamp of last update in epoch seconds, Comment
   * @throws InvalidArgumentException If $coPersonId not found
   * @throws RuntimeException For other errors
   */

  public function status($coProvisioningTargetId, $model, $id) {
    $ret = array();
    $ret['timestamp'] = null;
    $ret['comment'] = "";

    // We only provision CoPerson records, not CoGroup or any model.
    if($model->name != 'CoPerson') {
      return $ret;
    }

    // Pull the provisioning target configuration.
    $args = array();
    $args['conditions']['CoAccessdbProvisionerTarget.co_provisioning_target_id'] = $coProvisioningTargetId;
    $args['contain'] = false;

    $coProvisioningTargetData = $this->find('first', $args);
    $identifierType = $coProvisioningTargetData['CoAccessdbProvisionerTarget']['identifier_type'];

    // Pull the CO Person record and find the Identifier of type identifier_type 
    $args = array();
    $args['conditions']['CoPerson.id'] = $id;
    $args['contain'] = 'Identifier';

    $coPerson = $this->CoProvisioningTarget->Co->CoPerson->find('first', $args);

    $accessId = null;
    foreach($coPerson['Identifier'] as $identifier) {
      if($identifier['type'] == $identifierType) {
        $accessId = $identifier['identifier'];
        break;
      }
    }

    // We cannot find the Identifier of the type identifier_type so return unknown.
    if(empty($accessId)) {
      $ret['status'] = ProvisioningStatusEnum::Unknown;
      $ret['comment'] = _txt('er.accessdbprovisioner.id.none', array($identifierType));

      $msg = "status: Cannot find Identifier of type $identifierType for coPerson ID $id";
      $this->log($msg);

      return $ret;
    }

    // Pull the Server configuation
    $args = array();
    $args['conditions']['Server.id'] = $coProvisioningTargetData['CoAccessdbProvisionerTarget']['server_id'];
    $args['conditions']['Server.status'] = SuspendableStatusEnum::Active;
    $args['contain'] = array('HttpServer');

    $CoProvisioningTarget = new CoProvisioningTarget();
    $srvr = $CoProvisioningTarget->Co->Server->find('first', $args);
    
    if(empty($srvr)) {
      throw new InvalidArgumentException(_txt('er.notfound', array(_txt('ct.http_servers.1'), $coProvisioningTargetData['CoAccessdbProvisionerTarget']['server_id'])));
    }
    
    // Query the ACCESS DB for the profile.
    $this->Http = new CoHttpClient(array(
      'ssl_verify_peer' => $srvr['HttpServer']['ssl_verify_peer'],
      'ssl_verify_host' => $srvr['HttpServer']['ssl_verify_host']
    ));
    
    $this->Http->setBaseUrl($srvr['HttpServer']['serverurl']);
    $this->Http->setRequestOptions(array(
      'header' => array(
        'Accept'        => 'application/json',
        'Content-Type'  => 'application/json',
        'XA-REQUESTER'  => $srvr['HttpServer']['username'],
        'XA-API-KEY'    => $srvr['HttpServer']['password']
      )
    ));


    $response = $this->Http->get("/people/$accessId");

    if($response->code == 200) {
      $ret['status'] = ProvisioningStatusEnum::Provisioned;
    } elseif ($response->code == 404) {
      $ret['status'] = ProvisioningStatusEnum::NotProvisioned;
    } else {
      $msg = "ACCESS DB returned code " . $response->code;
      $this->log($msg);
      throw new RuntimeException($msg);
    }

    return $ret;
  }
  
  /**
   * Synchronize a CO Person.
   * 
   * @since  COmanage Registry v4.0.0
   * @param  array          $coAccessdbProvisionerTarget CoAccessdbProvisioningTarget
   * @param  array          $provisioningData       Provisioning Data
   * @return boolean        true
   * @throws RuntimeException
   */
  
  protected function syncPerson($coProvisioningTarget,
                                $provisioningData) {
    $coPersonId = $provisioningData['CoPerson']['id'];
    // Find the identifier of the requested identifier type
    
    $identifierType = $coProvisioningTarget['identifier_type'];
    $accessId = null;
    
    $ids = Hash::extract($provisioningData['Identifier'], '{n}[type='.$identifierType.']');

    if(empty($ids)) {
      throw new RuntimeException(_txt('er.accessdbprovisioner.id.none', array($identifierType)));
    }
    
    $accessId = $ids[0]['identifier'];

    // Determine if the profile already exists.
    $response = $this->Http->get("/people/$accessId");

    if($response->code == 200) {
      $profileExists = true;
      try {
        $profile = json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);
      } catch (Exception $e) {
        $msg = "Unable to decode message from ACCESS DB: ";
        $msg = $msg . print_r($e->getMessage(), true);
        $this->log($msg);
        throw new RuntimeException($msg);
      }
    } elseif ($response->code == 404) {
      $profileExists = false;
    } else {
      $msg = "ACCESS DB returned code " . $response->code;
      $this->log($msg);
      throw new RuntimeException($msg);
    }
    
    // Marshall the message body.
    $message = array();

    // We provision only the Primary Name.
    if($profileExists) {
      if($provisioningData['PrimaryName']['given'] != $profile['firstName']) {
        $message['firstName'] = $provisioningData['PrimaryName']['given'];
      }
      if($provisioningData['PrimaryName']['family'] != $profile['lastName']) {
        $message['lastName'] = $provisioningData['PrimaryName']['family'];
      }
      if(!empty($provisioningData['PrimaryName']['middle'])) {
        if(empty($profile['middleName']) || ($provisioningData['PrimaryName']['middle'] != $profile['middleName'])) {
          $message['middleName'] = $provisioningData['PrimaryName']['middle'];
        }
      } else {
        if(!empty($profile['middleName'])) {
          $message['middleName'] = null;
        }
      }
    } else {
      $message['firstName'] = $provisioningData['PrimaryName']['given'];
      $message['lastName'] = $provisioningData['PrimaryName']['family'];
      if(!empty($provisioningData['PrimaryName']['middle'])) {
        $message['middleName'] = $provisioningData['PrimaryName']['middle'];
      } else {
        $message['middleName'] = null;
      }
    }

    // We provision the first official email we find.
    foreach($provisioningData['EmailAddress'] as $email) {
      if($email['type'] == EmailAddressEnum::Official){
        if($profileExists) {
          if($email['mail'] != $profile['email']) {
            $message['email'] = $email['mail'];
          }
        } else {
          $message['email'] = $email['mail'];
        }
      }
    }

    // Find the CoPersonRole with affiliation affiliate and use the Organization
    // value to find the ACCESS Organization ID.
    $organizationName = null;
    foreach($provisioningData['CoPersonRole'] as $role) {
      if($role['affiliation'] == AffiliationEnum::Affiliate) {
        $organizationName = $role['o'];
      }
    }

    if(empty($organizationName)) {
      $msg = "CoPerson $coPersonId does not have CoPersonRole with affiliation Affiliate and Organization";
      $this->log($msg);
      throw new RuntimeException($msg);
    }

    $accessOrganizationModel = new AccessOrganization();

    $args = array();
    $args['conditions']['AccessOrganization.name'] = $organizationName;
    $args['contain'] = false;

    $accessOrganization = $accessOrganizationModel->find('first', $args);

    if(empty($accessOrganization)) {
      $msg = "Could not find ACCESS Organization with name $accessOrganization";
      $this->log($msg);
      throw new RuntimeException($msg);
    }

    $accessOrganizationId = $accessOrganization['AccessOrganization']['organization_id'];

    if($profileExists) {
      if($accessOrganizationId != $profile['organizationId']) {
        $message['organizationId'] = $accessOrganizationId;
      }
    } else {
        $message['organizationId'] = $accessOrganizationId;
    }

    // If no changes are necessary just return true.
    if($profileExists && empty($message)) {
      $msg = "ACCESS DB Provisioner: ";
      $msg = $msg . "No changes necessary for CoPerson $coPersonId with ACCESS ID $accessId";
      $this->log($msg);

      return true;
    }

    if($profileExists) {
      $verb = 'patch';
      $expectedCode = 204;
      $errorString = 'update';
      $msgString = 'Updated';
    } else {
      $verb = 'post';
      $expectedCode = 204;
      $errorString = 'create';
      $msgString = 'Created';
    }

    // Update or create profile.
    $response = $this->Http->{$verb}("/people/" . $accessId, json_encode($message));
    
    if($response->code != $expectedCode) {
      $msg = "ACCESS DB Provisioner: ";
      $msg = $msg . "Unable to $errorString profile for CoPerson $coPersonId with ACCESS ID $accessId";
      $this->log($msg);
      throw new RuntimeException($msg);
    } else {
      $msg = "ACCESS DB Provisioner: ";
      $msg = $msg . "$msgString profile for CoPerson $coPersonId with ACCESS ID $accessId";
      $this->log($msg);
    }
    
    return true;
  }
}
