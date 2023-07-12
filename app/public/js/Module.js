// Copyright (c) 2023 Rishik Tiwari
// 
// This Source Code Form is subject to the terms of the Mozilla Public
// License, v. 2.0. If a copy of the MPL was not distributed with this
// file, You can obtain one at http://mozilla.org/MPL/2.0/.

"use strict";

function Module(properties, shadowRoot = null)
{
    // properties = { module_id, dataset_id, page_id, permission }
    // shadowRoot = DOM element of module's mounted shadow root

    // DEFINITIONS
    const _NAMESPACE = 'MODULE/';
    const _MODULE_EVENTS = {
        UPDATED: 'data-updated',
        DELETED: 'data-deleted',
        CREATED: 'data-created'
    };
    const eventAttachments = new Map();


    // PRIVATE METHODS
    function _attach(event_name, handler) {
        if(typeof handler !== 'function') {
            console.warn('Module Bus: Handler is not a function!');
            return false;
        }
        event_name = _NAMESPACE + event_name;
        let token = App.Bus.attach(event_name, (data_in) => {
            // ensure only the handler for events meant for this module and dataset are invoked.
            if(data_in['module_id'] !== undefined
                && data_in['module_id'] === properties['module_id']
                && data_in['dataset_id'] === properties['dataset_id'])
            {
                handler(data_in['data']);
            }
        });

        if(token !== false) {
            eventAttachments.set(event_name, token);
            return true;
        }
        return false;
    }

    function _detach(event_name) {
        event_name = _NAMESPACE + event_name;
        const token = eventAttachments.get(event_name);

        if(token !== undefined && App.Bus.detach(event_name, token) === true) {
            eventAttachments.delete(event_name);
            return true;
        }
        return false;
    }


    // EXPOSED METHODS
    const subscribe = {
        dataUpdated(handler) {
            return _attach(_MODULE_EVENTS.UPDATED, handler);
        },
        dataCreated(handler) {
            return _attach(_MODULE_EVENTS.CREATED, handler);
        },
        dataDeleted(handler) {
            return _attach(_MODULE_EVENTS.DELETED, handler);
        }
    };

    const unsubscribe = {
        dataUpdated() {
            return _detach(_MODULE_EVENTS.UPDATED);
        },
        dataCreated() {
            return _detach(_MODULE_EVENTS.CREATED);
        },
        dataDeleted() {
            return _detach(_MODULE_EVENTS.DELETED);
        }
    };

    function getProperties()
    {
        return properties;
    }

    function getShadowRoot()
    {
        return shadowRoot;
    }

    return {
        subscribe,
        unsubscribe,
        getProperties,
        getShadowRoot
    };
}
