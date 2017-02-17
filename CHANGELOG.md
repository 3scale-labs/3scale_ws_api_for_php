# Change Log
All notable changes to this project will be documented in this file.

##[2.7.0] - 2017-02-16
### Added
- Added support for (Service Tokens)[https://support.3scale.net/docs/accounts/tokens]

### Changed
- The signature for `authrep` method has been changed from `authrep($appId, $appKey = null, $usage = null, $userId = null, $object = null, $no_body = null, $serviceId = null)` to `authrep($appId, $appKey = null, $credentials_or_service_id, $usage = null, $userId = null, $object = null, $no_body = null)`
- The signature for 'authrep_with_user_key' method has been changed from `authrep_with_user_key($userKey, $usage = null, $userId = null, $object = null, $no_body = null, $serviceId = null)` to `authrep_with_user_key($userKey, $credentials_or_service_id, $usage = null, $userId = null, $object = null, $no_body = null)`

