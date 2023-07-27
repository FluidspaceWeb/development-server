// Copyright (c) 2023 Rishik Tiwari
// 
// This Source Code Form is subject to the terms of the Mozilla Public
// License, v. 2.0. If a copy of the MPL was not distributed with this
// file, You can obtain one at http://mozilla.org/MPL/2.0/.

"use strict";

function IntegrationModule(_MODULE_ID, selected_account = null) {
    // DEFINITIONS
    const _EVENT_NAMESPACE = 'INTEGRATION/';
    const _SELECTED_ACCOUNT = new Map([['id', ''], ['access_type', '']]);
    const _EVENT_ATTACHMENTS = new Map();
    let _CREDENTIALS = null;
    
    // PRIVATE METHODS
    function _emit(event_name, detail = {}) { // ensures all events emitted have integration id.
        event_name = _EVENT_NAMESPACE + event_name;
        App.Bus.emit(event_name, _MODULE_ID, detail);
    }
    
    function _attach(event_name, handler_method) {
        if(typeof handler_method !== 'function') {
            console.warn('Integration Event Bus: Handler is not a function!');
            return false;
        }
        function _handler_proxy(integration_id, detail = {}) {
            if(integration_id === _MODULE_ID) { // call handler only if event meant of this integration
                handler_method(detail);
            }
        }
        
        event_name = _EVENT_NAMESPACE + event_name;
        if(_EVENT_ATTACHMENTS.get(event_name) !== undefined) { // allow only one handler per event
            console.warn('Integration Event Bus: Handler already defined.');
            return false;
        }
        const token = App.Bus.attach(event_name, _handler_proxy);
        if(token !== false){
            _EVENT_ATTACHMENTS.set(event_name, token);
            return true;
        }
    }
    
    function _detach(event_name) {
        event_name = _EVENT_NAMESPACE + event_name;
        const token = _EVENT_ATTACHMENTS.get(event_name);
        
        if(token === undefined) {
            console.warn('Integration Event Bus: No subscriptions');
            return true;
        }
        if(App.Bus.detach(event_name, token) === true) {
            _EVENT_ATTACHMENTS.delete(event_name);
            return true;
        }
        return false;
    }
    
    function _fetchCredentials() {
        const API = new IntegrationAPI(_MODULE_ID);
        return API.getRequestCredentials(account.getSelected());
    }
    
    
    // EXPOSED METHODS
    const subscribe = {
        onAddAccount(handler_method) {
            return _attach('sysASP-add-account', handler_method);
        },
        onAccountSelect(handler_method) {
            return _attach('sysASP-select-account', handler_method);
        },
        onAccountRemoved(handler_method) {
            return _attach('sysASP-account-removed', handler_method);
        }
    };
    const unsubscribe = {
        onAddAccount() {
            return _detach('sysASP-add-account');
        },
        onAccountSelect() {
            return _detach('sysASP-select-account');
        },
        onAccountRemoved() {
            return _detach('sysASP-account-removed');
        }
    };
    
    async function directRequest(request_url, headers_object, payload = null) {
        if(_CREDENTIALS === null || _CREDENTIALS['exp'] <= Math.ceil(Date.now()/1000)) {
            console.log('%cCredentials do not exist or expired!', 'font-weight: bold');
            // fetch credentials if not present or expired
            //TODO: expired session credentials may still be returned, can be checked before returning in php class.
            const credentialResp = await _fetchCredentials();
            if(credentialResp !== null && credentialResp['request_status'] === 1) {
                delete credentialResp['request_status'];
                _CREDENTIALS = Object.assign({}, credentialResp);
            }
            console.log('%cCredentials fetch complete', 'font-weight: bold');
        }
        if(_CREDENTIALS === null) {
            return Promise.reject('CREDENTIALS_NOT_FOUND');
        }
        if(!IntegrationAPI.isUrlAllowed(request_url, _CREDENTIALS['allowed_hosts'])) {
            return Promise.reject('ERR_URL_NOT_ALLOWED');
        }
        if(!(headers_object instanceof Headers)) {
            return Promise.reject('INVALID_HEADERS_TYPE');
        }
        if(headers_object.get('method') === null) {
            return Promise.reject('UNDEFINED_METHOD');
        }

        const request_method = headers_object.get('method');
        headers_object.delete('method'); // remove duplicate
        
        headers_object.set('Authorization', _CREDENTIALS['credentials']['token_type']+' '+_CREDENTIALS['credentials']['access_token']);
        const request_params = {
            'method': request_method,
            'headers': headers_object
        };
        if(payload !== null) {
            request_params['body'] = payload;
        }
        
        return fetch(request_url, request_params);
    }
    
    const account = {
        add(access_type, auth_provider_name)
        {
            const API = new IntegrationAPI(_MODULE_ID);
            return API.addAccount(access_type, auth_provider_name);
        },
        select(account_id, account_access_type)
        {
            if (!['private', 'shared'].includes(account_access_type) || account_id.trim() === '') {
                console.warn('Integration API: Invalid account_id or access_type!');
                return false;
            }
            _SELECTED_ACCOUNT.set('id', account_id);
            _SELECTED_ACCOUNT.set('access_type', account_access_type);
            _emit('account-selected', account.getSelected());
            return true;
        },
        clearSelected()
        {
            _SELECTED_ACCOUNT.set('id', '');
            _SELECTED_ACCOUNT.set('access_type', '');
            _emit('account-selected', account.getSelected());
        },
        getSelected()
        {
            return {
                'account_id': _SELECTED_ACCOUNT.get('id'),
                'access_type': _SELECTED_ACCOUNT.get('access_type')
            };
        }
    };
    
    function getConfig() {
        return JSON.parse(JSON.stringify(ModulePropsAPI.getModuleConfig(_MODULE_ID)));
    }

    // INITIALISATION
    if(selected_account !== null && typeof selected_account === 'object') { // initial selected account
        account.select(selected_account['id'], selected_account['access_type']);
    }
    
    return {
        directRequest,
        subscribe,
        unsubscribe,
        account,
        getConfig
    };
}
