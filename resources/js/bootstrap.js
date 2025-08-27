import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

window.Pusher = Pusher;

// Debug environment variables
const pusherKey = import.meta.env.VITE_PUSHER_APP_KEY;
const pusherCluster = import.meta.env.VITE_PUSHER_APP_CLUSTER;

console.log('Pusher Environment Variables:', { pusherKey, pusherCluster });

if (!pusherKey || !pusherCluster) {
    console.error('Missing required Pusher environment variables!');
    console.log('All env vars:', import.meta.env);
} else {
    window.Echo = new Echo({
        broadcaster: 'pusher',
        key: pusherKey,
        cluster: pusherCluster,
        forceTLS: true
    });
    console.log('Echo initialized successfully');
}
