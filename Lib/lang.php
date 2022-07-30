<?php
  
global $cm_lang, $cm_texts;

// When localizing, the number in format specifications (eg: %1$s) indicates the argument
// position as passed to _txt.  This can be used to process the arguments in
// a different order than they were passed.

$cm_accessdb_provisioner_texts['en_US'] = array(
  // Titles, per-controller
  'ct.co_accessdb_provisioner_targets.1'  => 'ACCESS DB Provisioner Target',
  'ct.co_accessdb_provisioner_targets.pl' => 'ACCESS DB Provisioner Targets',
  
  // Error messages
  'er.accessdbprovisioner.id.none'        => 'No identifier of type %1$s found for CO Person',
  
  // Plugin texts
  'pl.accessdbprovisioner.identifier_type'           => 'Identifier Type',
  'pl.accessdbprovisioner.identifier_type.desc'      => 'Identifier used as the ACCESS ID'
);
