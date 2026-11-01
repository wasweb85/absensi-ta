import { createApp } from 'vue';
import App from './App.vue';
import router from './router';
import BaseIcon from './components/BaseIcon.vue';
import { initPwa } from './composables/usePwa';

initPwa();

const app = createApp(App);
app.component('BaseIcon', BaseIcon);
app.use(router);
app.mount('#app');
