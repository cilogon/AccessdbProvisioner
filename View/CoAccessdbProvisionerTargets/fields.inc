<?php
  // Determine if fields are editable
  $e = false;
  
  if(($this->action == "add" && $permissions['add']) || ($this->action == "edit" && $permissions['edit']))
    $e = true;
    
  // We shouldn't get here if we don't have at least read permission, but check just in case
  
  if(!$e && !$permissions['view'])
    return false;
  
  // Add breadcrumbs
  print $this->element("coCrumb");

  $args = array();
  $args['plugin'] = null;
  $args['controller'] = 'co_provisioning_targets';
  $args['action'] = 'index';
  $args['co'] = $cur_co['Co']['id'];
  $this->Html->addCrumb(_txt('ct.co_provisioning_targets.pl'), $args);

  $args = array();
  $args['plugin'] = null;
  $args['controller'] = 'co_provisioning_targets';
  $args['action'] = 'edit';
  $args[] = $vv_ptid;
  $this->Html->addCrumb($co_accessdb_provisioner_targets[0]['CoProvisioningTarget']['description'], $args);

  $crumbTxt = _txt('op.config');
  $this->Html->addCrumb($crumbTxt);
  
  print $this->Form->hidden('co_id', array('default' => $cur_co['Co']['id'])) . "\n";
  print $this->Form->hidden('co_provisioning_target_id', array('default' => $vv_ptid)) . "\n";
?>
<?php if(empty($vv_servers)): ?>
<div class="co-info-topbox">
  <i class="material-icons">info</i>
  <?php print _txt('er.server.none', array(_txt('en.server', null, ServerEnum::HttpServer))); ?>
</div>
<?php else: // vv_servers ?>
<ul id="<?php print $this->action; ?>_co_accessdb_provisioner_target" class="fields form-list form-list-admin">
  <li>
    <div class="field-name">
      <?php print ($e ? $this->Form->label('server_id', _txt('fd.server')) : _txt('fd.server')); ?>
      <span class="required">*</span>
    </div>
    <div class="field-info">
      <?php
        $attrs = array();
        $attrs['value'] = (isset($co_accessdb_provisioner_targets[0]['CoAccessdbProvisionerTarget']['server_id']) 
                           ? $co_accessdb_provisioner_targets[0]['CoAccessdbProvisionerTarget']['server_id']
                           : null);
        // Since required=true, this will render blank by default but not allow save until a server is selected
        $attrs['empty'] = true;

        if($e) {
          print $this->Form->select('server_id',
                                    $vv_servers,
                                    $attrs);

          if($this->Form->isFieldError('server_id')) {
            print $this->Form->error('server_id');
          }
        } else {
          if(!empty($co_accessdb_provisioner_targets[0]['CoAccessdbProvisionerTarget']['server_id'])) {
            print filter_var($vv_servers[ $co_accessdb_provisioner_targets[0]['CoAccessdbProvisionerTarget']['server_id'] ], FILTER_SANITIZE_SPECIAL_CHARS);
          }
        }
      ?>
    </div>
  </li>
  <li>
    <div class="field-name">
      <div class="field-title">
        <?php print _txt('pl.accessdbprovisioner.identifier_type'); ?>
        <span class="required">*</span>
      </div>
      <div class="field-desc"><?php print _txt('pl.accessdbprovisioner.identifier_type.desc'); ?></div>
    </div>
    <div class="field-info">
      <?php
        $attrs = array();
        $attrs['value'] = (isset($co_accessdb_provisioner_targets[0]['CoAccessdbProvisionerTarget']['identifier_type'])
                           ? $co_accessdb_provisioner_targets[0]['CoAccessdbProvisionerTarget']['identifier_type']
                           : "");
        $attrs['empty'] = true;

        if($e) {
          print $this->Form->select('identifier_type',
                                    $vv_identifiers_types,
                                    $attrs);

          if($this->Form->isFieldError('identifier_type')) {
            print $this->Form->error('identifier_type');
          }
        } else {
          print _txt('en.identifier.type', null, filter_var($co_accessdb_provisioner_targets[0]['CoAccessdbProvisionerTarget']['identifier_type'],FILTER_SANITIZE_SPECIAL_CHARS));
        }
      ?>
    </div>
  </li>
  <?php if($e): ?>
  <li class="fields-submit">
    <div class="field-name">
      <span class="required"><?php print _txt('fd.req'); ?></span>
    </div>
    <div class="field-info">
      <?php print $this->Form->submit($submit_label); ?>
    </div>
  </li>
  <?php endif; ?>
</ul>
<?php endif; // vv_servers
