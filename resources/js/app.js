import { createApp } from 'vue';
import App from './App.vue';
import router from './router';
import { initPwa } from './composables/usePwa';

initPwa();

const app = createApp(App);
app.use(router);
app.mount('#app');
