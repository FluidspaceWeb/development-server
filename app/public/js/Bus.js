// Copyright (c) 2023 Rishik Tiwari
// 
// This Source Code Form is subject to the terms of the Mozilla Public
// License, v. 2.0. If a copy of the MPL was not distributed with this
// file, You can obtain one at http://mozilla.org/MPL/2.0/.

"use strict";

function Bus() {
    // DEFINITIONS
    const _NAMESPACES = {
        LOCAL: 'LOCAL',
        SYSTEM: 'SYSTEM',
        MODULE: 'MODULE',
        INTEGRATION: 'INTEGRATION'
    };

    let Events = {};
    const Queue = {};
    let previousHandlerId = 0;

    // PRIVATE METHODS
    function _isValidEventName(event_name)
    {
        if(typeof event_name === 'string' && event_name.trim().length > 0) {
            return true;
        }
        console.warn('Bus: Unacceptable event_name');
        return false;
    }

    function _isValidNamespace(namespace)
    {
        if(_NAMESPACES[namespace] !== undefined) {
            return true;
        }
        console.warn('Bus: Unacceptable namespace');
        return false;
    }

    function _extractNames(event_name)
    {
        let namespace = false, name = false;
        if(_isValidEventName(event_name) === true) {
            const names = event_name.split('/');

            if(names.length === 2) {
                if(_isValidNamespace(names[0]) === true) {
                    namespace = names[0];
                    name = names[1];
                }
            }
            else {
                console.warn('Bus: Invalid event name, unacceptable namespaces!');
            }

            return {namespace, name};
        }
    }

    function _getToken()
    {
        previousHandlerId++;
        return (previousHandlerId + '-' + Date.now());
    }

    function _addAttachmentToQueue(namespace, name, handler)
    {
        if(Queue[namespace] === undefined) {
            Queue[namespace] = {};
        }
        if(Queue[namespace][name] === undefined) {
            Queue[namespace][name] = new Map();
        }

        const token = _getToken();
        Queue[namespace][name].set(token, handler);
        return token;
    }

    // PUBLIC METHODS
    function add(event_name, callback = null)
    {
        const {namespace, name} = _extractNames(event_name);
        if(namespace === false) {
            return false;
        }
        if(callback !== null && typeof callback !== 'function') {
            console.warn('Bus: Event not added, provided callback is not a function.');
            return false
        }

        Events[namespace] = Events[namespace] || {};
        if(Events[namespace][name] !== undefined) {
            console.warn('Bus: Event already exists.');
            return false;
        }

        Events[namespace][name] = {
            'cb': callback,
            'listeners': new Map()
        };

        // add events from queue if any
        if(Queue[namespace] !== undefined && Queue[namespace][name] !== undefined) {
            Queue[namespace][name].forEach((handler, token) => {
                Events[namespace][name].listeners.set(token, handler);
            });
            Queue[namespace][name].clear();
            delete Queue[namespace][name];
        }
        return true;
    }

    function remove(event_name)
    {
        const {namespace, name} = _extractNames(event_name);
        if(namespace === false) {
            return false;
        }

        if(Events[namespace] === undefined || Events[namespace][name] === undefined) {
            console.warn('Bus: Event to remove does not exist!');
            return false;
        }

        Events[namespace][name].listeners.clear(); // listeners is a Map()
        delete Events[namespace][name];
        return true;
    }

    function attach(event_name, handler, addToQueue = true)
    {
        const {namespace, name} = _extractNames(event_name);
        if(namespace === false) {
            return false;
        }
        if(typeof handler !== 'function') {
            console.warn('Bus: Handler is not a function!');
            return false;
        }

        // if event does not exist then add to queue
        if(Events[namespace] === undefined || Events[namespace][name] === undefined) {
            if(addToQueue === true) {
                console.warn('Bus: Event to attach does not exist, adding to queue!');
                return _addAttachmentToQueue(namespace, name, handler);
            }
            console.warn('Bus: Event to attach does not exist!');
            return false;
        }
        const token = _getToken();
        Events[namespace][name].listeners.set(token, handler);
        return token;
    }

    function detach(event_name, token)
    {
        const {namespace, name} = _extractNames(event_name);
        if(namespace === false) {
            return false;
        }
        if(typeof token !== 'string' || token.length === 0) {
            console.warn('Bus: Invalid token!');
            return false;
        }

        if(Events[namespace] === undefined || Events[namespace][name] === undefined) {
            console.warn('Bus: Provided event does not exist!');
        }
        else if(Events[namespace][name].listeners.delete(token) === false) {
            console.warn('Bus: Token for handler of the provided event does not exist!');
        }

        return true;
    }

    function emit(event_name, ...args)
    {
        const {namespace, name} = _extractNames(event_name);
        if(namespace === false) {
            return false;
        }

        if(Events[namespace] === undefined || Events[namespace][name] === undefined) {
            console.warn('Bus: Event to emit-in is not defined!');
            return false;
        }

        if(Events[namespace][name].cb !== null) {
            // Callback the event creator if specified
            Events[namespace][name].cb();
        }

        Events[namespace][name].listeners.forEach((func) => {
            func.apply(this, args);
        });
        return true;
    }

    return {
        Events,
        Queue,
        _NAMESPACES,
        add,
        remove,
        attach,
        detach,
        emit
    }
}
