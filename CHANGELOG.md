# Change Log
All notable changes to this project will be documented in this file.

## 2.7.1

### Added
- Added client header ```X-3scale-User-Agent: plugin-php-v{version-number} [PR #17](https://github.com/3scale/3scale_ws_api_for_php/pull/17)

### Changed
- Allow custom host and port configurable for 3scale On premise SAAS platform [PR #16](https://github.com/3scale/3scale_ws_api_for_php/pull/16)  Note: For example, the signature is changed from ```$url = "http://" . $this->getHost() . "/transactions/authorize.xml"``` to ```$url = $this->getHost() . "/transactions/oauth_authorize.xml";``` for endpoints

##[2.7.0] - 2017-02-16
### Added
- Added support for (Service Tokens)[https://support.3scale.net/docs/accounts/tokens]

### Changed
- The signature for `authrep` method has been changed from `authrep($appId, $appKey = null, $usage = null, $userId = null, $object = null, $no_body = null, $serviceId = null)` to `authrep($appId, $appKey = null, $credentials_or_service_id, $usage = null, $userId = null, $object = null, $no_body = null)`
- The signature for 'authrep_with_user_key' method has been changed from `authrep_with_user_key($userKey, $usage = null, $userId = null, $object = null, $no_body = null, $serviceId = null)` to `authrep_with_user_key($userKey, $credentials_or_service_id, $usage = null, $userId = null, $object = null, $no_body = null)`

