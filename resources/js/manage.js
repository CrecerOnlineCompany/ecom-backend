import { createApp } from 'vue';
import { createPinia } from 'pinia';
import ManageApp from './manage/ManageApp.vue';

createApp(ManageApp).use(createPinia()).mount('#manage-app');
