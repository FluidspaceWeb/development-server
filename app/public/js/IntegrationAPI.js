// Copyright (c) 2023 Rishik Tiwari
// 
// This Source Code Form is subject to the terms of the Mozilla Public
// License, v. 2.0. If a copy of the MPL was not distributed with this
// file, You can obtain one at http://mozilla.org/MPL/2.0/.

"use strict";

/** This implementation of Toast is only for development purpose. */
class Toast {
    static TYPE_SUCCESS = 'success';
    static TYPE_ERROR = 'error';
    static TYPE_WARN = 'warn';
    static TYPE_INFORM = 'inform';

    /**
     * 
     * @param {string} origin_name
     * @param {'success' | 'error' | 'warn' | 'inform'} severity_type
     * @param {string} message
     */
    constructor(origin_name, severity_type, message = '')
    {
        let colorMap = {
            'success': 'lime',
            'error': 'red',
            'warn': 'orange',
            'inform': 'cyan'
        };
        
        let style = 'font-size: 14px; color: '+ colorMap[severity_type];
        if(typeof message === 'string') {
            console.log(`%c${origin_name} says\n> %s`, style, message);
        }
        else {
            console.log(`%c${origin_name} logs`, style);
            console.log(message);
        }
    }
}

class IntegrationAPI {
    #integration_id;
    #module_config;
    #module_fullName;
    #provider_config = null;
    #_DEV_SERVER_URL = '';
    #_BASE_URL = '';
    #_OAUTH2_REDIRECT_URI = '';

    constructor(integration_id)
    {
        this.#integration_id = integration_id;
        this.#module_config = ModulePropsAPI.getModuleConfig(integration_id);
        this.#module_fullName = this.#module_config['namespace'] + '_' + this.#module_config['mod_name'];

        const moduleConfig = JSON.parse(document.getElementById('config').text);
        this.#_DEV_SERVER_URL = moduleConfig['serverUrl'];
        this.#_BASE_URL = moduleConfig['serverUrl'] + '/routes/integration.route.php?action=';
        this.#_OAUTH2_REDIRECT_URI = moduleConfig['serverUrl'] + '/integration-OAuth2Callback.html';
    }

    static #PromiseReject(message = '')
    {
        return Promise.reject(new Error(message));
    }

    #getDefaultHeaders = () => {
        const headers = {
            'Content-Type': 'application/json',
            'x-wsid': 'XwQ8E4gpVXcvjdz09MQV',   // Dummy space id, not checked in development env
            'x-acsrf-token': '1234567890',      // Dummy token, not checked in development env
            'x-module-id': this.#integration_id,
            'x-module-fn': this.#module_fullName,
            'x-module-type': 'integration',
        };
        return (new Headers(headers));
    };

    #getAuthConfig = async (auth_provider_name) => { //  fetches auth config based on provider_name
        if (this.#provider_config !== null) {
            return Promise.resolve(this.#provider_config);
        }

        return await fetch(this.#_BASE_URL + 'getAuthProviderConfig', {
            method: 'POST',
            headers: this.#getDefaultHeaders(),
            body: JSON.stringify({'provider_name': auth_provider_name})
        })
            .then((resp) => resp.json())
            .then((data) => {
                this.#provider_config = data;
                return data;
            })
            .catch((err) => {
                console.error('An error occurred while fetching auth provider config for integration ' + this.#integration_id, err);
                new Toast('Fluidspace', Toast.TYPE_ERROR,
                    'An error occurred while fetching authorisation configuration for integration: ' + this.#module_config['title']
                );
                return null;
            });
    };
    
    #openConsentWindow = (window_url) => {
        const windowObjectReference = window.open(window_url, '_blank');

        return new Promise((resolve, reject) => {
            const poller = setInterval(() => {
                if (windowObjectReference.closed === true) {
                    _clear();
                    console.warn('Auth consent window terminated');
                    reject('CONSENT_WINDOW_CLOSED');
                }
            }, 1000);

            const timeout = setTimeout(() => { // timeout the promise at 3min if no response received.
                _clear();
                reject('CONSENT_CALLBACK_TIMEOUT');
            }, 180000);

            function _clear()
            {
                clearTimeout(timeout);
                clearInterval(poller);
                window.removeEventListener('message', receivedCallbackEvent);
            }

            function receivedCallbackEvent(e)
            {
                // if (e.origin === window.location.origin) { //DISABLED ONLY FOR DEVELOPMENT PURPOSE
                    _clear();
                    const msg = JSON.parse(e.data);
                    resolve(msg);
                // }
            }

            window.addEventListener('message', receivedCallbackEvent, false);
        });
    };

    static isUrlAllowed = (url_string, allowed_hosts) => {
        if(typeof url_string !== 'string') {
            console.warn('Integration API: URL must be string type!');
            return false;
        }
        if(!Array.isArray(allowed_hosts)) {
            console.warn('Integration API: allowed_hosts must be array type!');
            return false;
        }
        
        const url = new URL(url_string);
        if (url.protocol !== 'https:') {
            console.warn('Integration API: Only https is allowed');
            return false;
        }

        let url_host = url.protocol + '//' + url.hostname;
        return allowed_hosts.includes(url_host);
    };
    
    #saveNewOAuth2Account = async (access_type, auth_provider_name, auth_code) => {
        return await fetch(this.#_BASE_URL + 'addAccount', {
            method: 'POST',
            headers: this.#getDefaultHeaders(),
            body: JSON.stringify({
                'provider_name': auth_provider_name,
                'auth_code': auth_code,
                'access_type': access_type
            })
        })
            .then((resp) => resp.json())
            .then((data) => {
                if(data['request_status'] === 1) {
                    new Toast('Fluidspace', Toast.TYPE_SUCCESS, 'Account saved of integration: ' + this.#module_config['title']);
                }
                else {
                    new Toast('Fluidspace', Toast.TYPE_WARN, 'Could not save account of integration: ' + this.#module_config['title']);
                }
                return data;
            })
            .catch((err) => {
                console.error('An error occurred, failed to save account of integration: ' + this.#integration_id, err);
                new Toast('Fluidspace', Toast.TYPE_ERROR,
                    'An error occurred, failed to save account authorisation of integration: ' + this.#module_config['title']
                );
                return err;
            });
    };

    #refreshAccountsList = (access_type, new_account_id) => {
        this.getAccounts(access_type)
            .then((resp) => {
                if (resp['request_status'] === 1) {
                    const accounts = resp['accounts'] || [];
                    const newAccount = accounts.find((account) => account['_id'] === new_account_id) || null;
                    App.Bus.emit('INTEGRATION/account-added', this.#integration_id, access_type, newAccount);
                }
                else {
                    new Toast('Fluidspace', Toast.TYPE_WARN,
                        'Could not refresh account\'s list of integration: ' + this.#module_config['title'] + '. Please reload workspace to see new accounts.');
                }
            })
            .catch((err) => {
                console.warn('Error in updating integration ' + this.#integration_id + ' accounts list.', err);
                new Toast('Fluidspace', Toast.TYPE_ERROR,
                    'An error occurred while updating account\'s list of integration: ' + this.#module_config['title'] + '. Reload workspace to see changes.');
            });
    };
    
    #handler__OAuth2 = async (access_type, auth_provider_name) => {
        const authorisation_url = this.#provider_config['auth_grant_url'];
        if (authorisation_url === undefined || typeof authorisation_url !== 'string' || authorisation_url.trim() === '') {
            return IntegrationAPI.#PromiseReject('ERR_INVALID_AUTH_URL');
        }

        if (!IntegrationAPI.isUrlAllowed(authorisation_url, this.#provider_config['allowed_hosts'])) {
            return IntegrationAPI.#PromiseReject('ERR_URL_NOT_ALLOWED');
        }

        const auth_code_url = new URL(authorisation_url);
        const auth_code_url_params = auth_code_url.searchParams;
        auth_code_url_params.set('scope', this.#provider_config['non_secret']['scope']);
        auth_code_url_params.set('client_id', this.#provider_config['non_secret']['client_id']);
        auth_code_url_params.set('access_type', 'offline');
        auth_code_url_params.set('include_granted_scopes', 'true');
        auth_code_url_params.set('response_type', 'code');
        // auth_code_url_params.set('redirect_uri', window.location.origin + '/assets/html/integration-OAuth2Callback.html');
        auth_code_url_params.set('redirect_uri', this.#_OAUTH2_REDIRECT_URI);

        try { // get user consent
            /**
             * Consent values received after Authorisation from OAuth2 provider initiated in another window/tab.
             * @type {object} consent
             * @prop {string|null} code - the auth_code
             * @prop {string|null} scope - granted scopes
             * @prop {string|null} error - message string if any error occurred
             * */
            const consent = await this.#openConsentWindow(auth_code_url.toString());
            
            if (consent['code'] !== null && consent['code'] !== '') {
                new Toast('Fluidspace', Toast.TYPE_SUCCESS,
                    'Successful authorisation for integration: ' + this.#module_config['title'] + ', Saving account...');
                
                let accountSaved = await this.#saveNewOAuth2Account(access_type, auth_provider_name, consent['code']);
                console.log(accountSaved); // logged only for development purpose
                
                if(accountSaved['request_status'] === 1) {
                    // TODO:
                    // this.#refreshAccountsList(access_type, accountSaved['_id']); // fetches and update accounts list
                }
                else if(accountSaved['request_status'] === -2) {
                    new Toast('Fluidspace', Toast.TYPE_WARN, accountSaved['message']);
                    accountSaved = false;
                }
                else {
                    console.log('Integration account saving failed.', accountSaved);
                    new Toast('Fluidspace', Toast.TYPE_ERROR, accountSaved['message'] || 'An unexpected error occurred while saving account.');
                    accountSaved = false;
                }

                return {
                    'request_status': (accountSaved !== false)? 1 : 0,
                    'response': accountSaved
                };
            }
            return Promise.reject(consent);
        }
        catch (err) {
            new Toast('Fluidspace', Toast.TYPE_ERROR, 'Could not complete authorisation for integration: ' + this.#module_config['title']);
            console.log(err);
            return await Promise.reject(err);
        }
    };

    getAccounts = async (access_type) => {
        return await fetch(this.#_BASE_URL + 'getAccounts', {
            'method': 'POST',
            'headers': this.#getDefaultHeaders(),
            'body': JSON.stringify({'access_type': access_type})
        }).then(resp => resp.json());
    };

    addAccount = async (access_type = 'private', auth_provider_name) => {
        if (typeof auth_provider_name !== 'string' || auth_provider_name.trim() === '') {
            return IntegrationAPI.#PromiseReject('INVALID_PROVIDER_NAME');
        }
        /* DISABLED FOR DEVELOPMENT PURPOSE
        const accountsCount = App.Factory.Integrations.getIntegrationAccountsCount();
        if(accountsCount.private === 2 || accountsCount.shared === 2) {
            new Toast('Fluidspace', Toast.TYPE_WARN, 'Cannot add more accounts, limit reached.');
            return IntegrationAPI.#PromiseReject('ACCOUNT_LIMIT_REACHED');
        } */

        // fetch auth config based on pName
        const pConfig = await this.#getAuthConfig(auth_provider_name);
        if (pConfig === null) {
            return IntegrationAPI.#PromiseReject('FETCH_ERR_AUTH_CONFIG');
        }

        // identify auth_type
        if (pConfig['auth_type'] === 'OAuth2') {
            return this.#handler__OAuth2(access_type, auth_provider_name);
        }
        
        return IntegrationAPI.#PromiseReject('ERR_UNKNOWN_AUTH_TYPE');
    };
    
    #addReqCredentialToSessionStorage = (account_id, credential_object) => {
        const reqCredentialCacheString = sessionStorage.getItem('reqCredentialCache');
        let reqCredentialCache = {};
        if (reqCredentialCacheString !== null) {
            try {
                reqCredentialCache = JSON.parse(reqCredentialCacheString);
            } catch(e) {}
            reqCredentialCache[account_id] = {};
        }
        reqCredentialCache[account_id] = credential_object;
        sessionStorage.setItem('reqCredentialCache', JSON.stringify(reqCredentialCache));
        console.log('request credentials cached for future use. delete key reqCredentialCache from LocalStorage if facing any issue.');
        return true;
    }
    

    getRequestCredentials = async (selected_account) => {
        if(selected_account['account_id'] === '' || selected_account['access_type'] === '') {
            return Promise.reject('UNDEFINED_SELECTED_ACCOUNT');
        }

        const headers = this.#getDefaultHeaders();
        headers.set('x-integration-account-id', selected_account['account_id']);
        headers.set('x-integration-access-type', selected_account['access_type']);
        
        // check and respond if valid credentials exist in cache, else fetch
        // FOLLOWING IMPLEMENTATION ONLY FOR DEVELOPMENT PURPOSE
        const reqCredentialCacheString = sessionStorage.getItem('reqCredentialCache');
        if (reqCredentialCacheString !== null) {
            const reqCredentialCache = JSON.parse(reqCredentialCacheString);
            if (reqCredentialCache[selected_account['account_id']] !== undefined
                && reqCredentialCache[selected_account['account_id']]['credentials']['exp'] > Math.ceil(Date.now()/1000)
            ) {
                console.log('using reqCredentials from cache.');
                return reqCredentialCache[selected_account['account_id']];
            }
        }
        
        console.log('fetching fresh reqCredentials...');
        return await fetch(this.#_BASE_URL + 'getRequestCredentials', {
            'method': 'GET',
            'headers': headers,
        })
            .then((resp) => resp.json())
            .then((resp) => {
                this.#addReqCredentialToSessionStorage(selected_account['account_id'], resp);
                return resp;
            })
            .catch((err) => {
                console.error('Could not fetch request credentials', err);
                return null;
            });
    };

    removeAccount = () => {
        console.warn('Method not available');
    };
}
