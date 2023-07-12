// Copyright (c) 2023 Rishik Tiwari
// 
// This Source Code Form is subject to the terms of the Mozilla Public
// License, v. 2.0. If a copy of the MPL was not distributed with this
// file, You can obtain one at http://mozilla.org/MPL/2.0/.

'use strict';

class ModuleDataAPI
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
        this.#module_config = Object.assign({}, window.App['ModuleConfigs'][module_id]); //clones moduleConfig from global variable
        this.#module_fullName = this.#module_config['namespace'] + '_' + this.#module_config['mod_name'];

        const appConfig = JSON.parse(document.getElementById('appConfig').text);
        this.#_BASE_URL = appConfig.serverUrl + '/routes/moduleDevAPI.route.php?action=';

        //FOR DEVELOPMENT PURPOSE ONLY
        this.#httpHeaders = {
            'Content-Type': 'application/json',
            'x-allowed-perm': 9,
            'x-wsid': 'XwQ8E4gpVXcvjdz09MQV', // Dummy space id, not checked in development env
            'x-module-id': module_id,
            'x-module-fn': this.#module_fullName,
            'x-dataset-id': this.#dataset_id
        };
    }
    
    #emitDataChangeEvent = (response_json) => {
        // emits only for insert, update, delete.
        /*
        if((response_json['insert_status'] !== undefined && response_json['insert_status'] === 1)
            || (response_json['update_status'] !== undefined && response_json['update_status'] === 1)
            || (response_json['delete_status'] !== undefined && response_json['delete_status'] === 1)) {
            App.Bus.emit('MODULE/dataServe-changed', {
                module_id: this.#module_id,
                dataset_id: this.#dataset_id,
                page_id: App.activePageId,
                mod_fn: this.#module_fullName,
                data: response_json
            });
        }
        */
    };

    #handleResponse = async (response_promise) => {
        // This method only handles the responses which need to be emitted
        ModuleDataAPI.handleResponseCode(response_promise.status);
        return await response_promise.json()
            .then((resp_json) => {
                this.#emitDataChangeEvent(resp_json);
                return resp_json;
            })
            .catch((err) => {
                console.error('Module Data API: JSON response parsing error!', err);
                return Promise.reject(new Error('PARSING_ERROR'));
            });
    };

    static handleResponseCode(http_response_code)
    {
        switch (http_response_code) {
            case 401:
                alert('User not logged in or session has expired. \n\nPlease login!');
                window.location.replace('/login');
                break;

            case 500:
                alert('Internal Server Error. \n\nPlease wait for sometime, refresh the page and try again!\n\nWe regret the inconvenience caused.');
                break;
        }
    }

    fetch = async (parameters = {}) => {
        return await fetch(this.#_BASE_URL + 'fetch', {
                method: 'POST',
                headers: this.#httpHeaders,
                body: JSON.stringify(parameters)
            })
            .then(this.#handleResponse)
            .catch((err) => {
                alert('Network Error, Could not reach server! Please check your internet and retry.');
                console.error('Network Error Occurred: ', err);
                return Promise.reject(new Error('NETWORK_ERROR'));
            });
    };

    fetchFromCompanion = async (companion_id, filtersAndOptions = {}) => {
        return await fetch(this.#_BASE_URL + 'fetchCompanionData', {
            method: 'POST',
            headers: this.#httpHeaders,
            body: JSON.stringify({
                'companion_id': companion_id,
                'filtersAndOptions': filtersAndOptions
            })
        })
            .then(this.#handleResponse)
            .catch((err) => {
                alert('Network Error, Could not reach server! Please check your internet and retry.');
                console.error('Network Error Occurred: ', err);
                return Promise.reject(new Error('NETWORK_ERROR'));
            });
    };

    insert = async (documents_to_insert) => {
        return await fetch(this.#_BASE_URL + 'insert', {
                method: 'POST',
                headers: this.#httpHeaders,
                body: JSON.stringify(documents_to_insert)
            })
            .then(this.#handleResponse)
            .catch((err) => {
                alert('Network Error, Could not reach server! Please check your internet and retry.');
                console.error('Network Error Occurred: ', err);
                return Promise.reject(new Error('NETWORK_ERROR'));
            });
    };

    delete = async (ids_to_delete) => {
        return await fetch(this.#_BASE_URL + 'delete', {
                method: 'POST',
                headers: this.#httpHeaders,
                body: JSON.stringify({
                    "document_ids": ids_to_delete
                })
            })
            .then(this.#handleResponse)
            .catch((err) => {
                alert('Network Error, Could not reach server! Please check your internet and retry.');
                console.error('Network Error Occurred: ', err);
                return Promise.reject(new Error('NETWORK_ERROR'));
            });
    };

    update = async (document_updates) => {
        return await fetch(this.#_BASE_URL + 'update', {
                method: 'POST',
                headers: this.#httpHeaders,
                body: JSON.stringify({
                    "_id": document_updates['_id'] || '',
                    "updates": document_updates['updates'] || []
                })
            })
            .then(this.#handleResponse)
            .catch((err) => {
                alert('Network Error, Could not reach server! Please check your internet and retry.');
                console.error('Network Error Occurred: ', err);
                return Promise.reject(new Error('NETWORK_ERROR'));
            });
    };
}
