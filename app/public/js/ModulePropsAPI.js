// Copyright (c) 2023 Rishik Tiwari
// 
// This Source Code Form is subject to the terms of the Mozilla Public
// License, v. 2.0. If a copy of the MPL was not distributed with this
// file, You can obtain one at http://mozilla.org/MPL/2.0/.

'use strict';

class ModulePropsAPI
{
    #module_id;
    #dataset_id;
    #module_config;
    #module_fullName;
    #httpHeaders;
    #_BASE_URL;
    
    constructor(module_id, dataset_id) {
        this.#module_id = module_id;
        this.#dataset_id = dataset_id;
        this.#module_config = ModulePropsAPI.getModuleConfig(module_id);
        this.#module_fullName = this.#module_config['namespace'] + '_' + this.#module_config['mod_name'];

        const appConfig = JSON.parse(document.getElementById('appConfig').text);
        this.#_BASE_URL = appConfig.serverUrl + '/routes/modulePropsDev.route.php?action=';

        //FOR DEVELOPMENT PURPOSE ONLY
        this.#httpHeaders = {
            'Content-Type': 'application/json',
            'x-allowed-perm': 9,
            'x-wsid': 'YWagNoRMxpFZMk91GaW5',
            'x-module-id': module_id,
            'x-module-fn': this.#module_fullName, //TODO: this may not be required, because full name should be searched using module id
            'x-dataset-id': this.#dataset_id
        };
    }

    static getModuleConfig(module_id)
    {
        //gets and clones moduleConfig from global variable
        return Object.assign({}, window.App['ModuleConfigs'][module_id]);
    }

    static #getErrorPromise(err_message) {
        return Promise.reject(new Error(err_message));
    }
    
    #handleResponse = async (response_promise) => {
        // This method only handles the responses which need to be emitted
        ModuleDataAPI.handleResponseCode(response_promise.status);
        return await response_promise.json()
            .then((resp_json) => {
                return resp_json;
            })
            .catch((parsingErr) => {
                console.error('An error occurred while parsing Prop response!', parsingErr);
                alert('An error occurred while parsing Prop response for: '+this.#module_fullName);

                return ModulePropsAPI.#getErrorPromise('PARSING_ERROR');
            });
    };
    
    #makeRequest = async (endpointParameter, requestPayload, method = 'POST') => {
        let endpoint_url = this.#_BASE_URL + endpointParameter;
        let fetch_init = {
            'method': method,
            'headers': this.#httpHeaders,
        };

        if(method === 'POST') {
            fetch_init['body'] = JSON.stringify(requestPayload);
        }
        else {
            requestPayload = requestPayload.trim();
            if (requestPayload !== '') {
                endpoint_url += '?'+encodeURIComponent(requestPayload);
            }
        }
        
        return await fetch(endpoint_url, fetch_init)
            .then(this.#handleResponse)
            .catch((err) => {
                alert('Network Error, Could not reach server! Please check your internet.');
                console.error('Network Error Occurred: ', err);
                return ModulePropsAPI.#getErrorPromise('NETWORK_ERROR');
            });
    };

    getProps = (filtersAndOptions = {}) => {
        return this.#makeRequest('fetch', filtersAndOptions);
    };

    insertProps = (props_array = []) => {
        if(Array.isArray(props_array)) {
            return this.#makeRequest('insert', {
                'props': props_array
            });
        }
        console.warn('Props API: Parameter should be Array, got ' + typeof props_array);
        return ModulePropsAPI.#getErrorPromise('INVALID_TYPE');
    };

    updateProp = (prop_id, prop_updates = {}) => {
        if(typeof prop_id === 'string' && typeof prop_updates === 'object') {
            return this.#makeRequest('update', {
                '_id': prop_id,
                'updates': prop_updates
            });
        }
        console.warn('Props API: prop_id should be of string and prop_updates of object type.');
        return ModulePropsAPI.#getErrorPromise('INVALID_TYPE');
    };

    deleteProps = (prop_ids = []) => {
        if(Array.isArray(prop_ids)) {
            for(let i = 0; i < prop_ids.length; i++) {
                if(typeof prop_ids[i] !== 'string') {
                    console.warn(`Props API: prop_ids should be array of ID strings, got ${typeof prop_ids[i]} at index ${i}`);
                    return ModulePropsAPI.#getErrorPromise('INVALID_TYPE');
                }
            }
            
            return this.#makeRequest('delete', {
                'prop_ids': prop_ids
            });
        }
        console.warn('Props API: Parameter should be array, got ' + typeof prop_ids);
        return ModulePropsAPI.#getErrorPromise('INVALID_TYPE');
    };
    
    getWorkspaceUsers = (forceFetch = false) => {
        let shouldFetch = true;
        if(App['WorkspaceUsers'] !== undefined && typeof App['WorkspaceUsers'] === 'object' && App['WorkspaceUsers']['users'] !== undefined) {
            if((Date.now() - App['WorkspaceUsers']['last_fetched']) < 1800000) {
                // if last fetched less than 30min ago (in ms)
                shouldFetch = false;
            }
        }

        if(shouldFetch || forceFetch) {
            return this.#makeRequest('getWorkspaceUsers', '', 'GET')
                .then((users) => {
                    App['WorkspaceUsers'] = {
                        'last_fetched': Date.now(),
                        'users': users
                    };
                    return users;
                });
        }

        return Promise.resolve(App['WorkspaceUsers']['users']);
    };
    
    getUser = () => {
        if(App.Workspace !== undefined && App.Workspace.user !== undefined) {
            return Promise.resolve(Object.assign({}, App.Workspace.user));
        }
        return Promise.reject(new Error('USER_INFO_UNAVAILABLE'));
    };

    getStaticConfig = () => {
        return ModulePropsAPI.getModuleConfig(this.#module_id);
    };
}
