import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

console.log('VITE_PUSHER_APP_KEY:', import.meta.env.VITE_PUSHER_APP_KEY);
console.log('VITE_PUSHER_APP_CLUSTER:', import.meta.env.VITE_PUSHER_APP_CLUSTER);

const initializeEcho = () => {
    const pusherKey = import.meta.env.VITE_PUSHER_APP_KEY;

    if (!pusherKey || pusherKey === 'undefined' || pusherKey === 'null' || pusherKey === '') {
        console.warn('Pusher app key not found or invalid. Echo will be a mock object.');
        // Create a mock Echo object to prevent errors
        window.Echo = {
            private: (channel) => {
                console.log(`[Mock Echo] Private channel requested: ${channel}`);
                return {
                    listen: (event, callback) => {
                        console.log(`[Mock Echo] Would listen to ${event} on ${channel}`);
                        return { stop: () => {} };
                    },
                    notification: (callback) => {
                        console.log(`[Mock Echo] Would listen to notifications on ${channel}`);
                        return { stop: () => {} };
                    }
                };
            },
            channel: (channel) => {
                console.log(`[Mock Echo] Channel requested: ${channel}`);
                return {
                    listen: (event, callback) => {
                        console.log(`[Mock Echo] Would listen to ${event} on ${channel}`);
                        return { stop: () => {} };
                    }
                };
            },
            join: () => {},
            leave: () => {},
            socketId: () => 'mock-socket-id'
        };
    } else {
        try {
            window.Echo = new Echo({
                broadcaster: 'pusher',
                key: pusherKey,
                cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER || 'mt1',
                wsHost: import.meta.env.VITE_PUSHER_HOST || `ws-${import.meta.env.VITE_PUSHER_APP_CLUSTER || 'mt1'}.pusher.com`,
                wsPort: import.meta.env.VITE_PUSHER_PORT || 80,
                wssPort: import.meta.env.VITE_PUSHER_PORT || 443,
                forceTLS: (import.meta.env.VITE_PUSHER_SCHEME || 'https') === 'https',
                enabledTransports: ['ws', 'wss'],
                encrypted: (import.meta.env.VITE_PUSHER_APP_ENCRYPTED || 'true') === 'true',
                disableStats: true,
            });
            console.log('Echo initialized successfully with Pusher');
        } catch (error) {
            console.error('Failed to initialize Echo:', error);
            window.Echo = null;
        }
    }
};

initializeEcho();
