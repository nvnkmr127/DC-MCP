import axios from 'axios';

axios.defaults.withCredentials = true;
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

(window as any).axios = axios;
