<template>
    <main class="min-h-screen bg-[#f7f7fb] text-slate-950">
        <div class="bg-gradient-to-r from-violet-700 to-indigo-600 px-4 py-2 text-center text-xs font-semibold text-white sm:text-sm">
            Envíos a todo el país · 3 cuotas sin interés
        </div>

        <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex h-16 items-center gap-3">
                    <button class="grid h-10 w-10 place-items-center rounded-xl text-slate-600 md:hidden" type="button" @click="mobileMenu = !mobileMenu">
                        <Menu :size="22" />
                    </button>
                    <button class="flex shrink-0 items-center gap-2" type="button" @click="goHome">
                        <span class="grid h-10 w-10 place-items-center rounded-xl bg-gradient-to-br from-violet-600 to-indigo-600 text-sm font-black text-white">{{ storeInitials }}</span>
                        <strong class="hidden text-xl font-black tracking-tight text-violet-700 sm:block">{{ storeConfig.name }}</strong>
                    </button>
                    <label class="relative mx-auto hidden max-w-xl flex-1 md:block">
                        <Search class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400" :size="18" />
                        <input v-model="searchQuery" class="h-11 w-full rounded-xl border border-slate-200 bg-slate-50 pl-11 pr-4 text-sm outline-none transition focus:border-violet-400 focus:bg-white" placeholder="Buscar productos...">
                    </label>
                    <button class="hidden items-center gap-2 rounded-xl px-3 py-2 text-sm font-semibold text-slate-600 sm:flex" type="button">
                        <User :size="19" /> Cuenta
                    </button>
                    <button class="relative grid h-10 w-10 place-items-center rounded-xl border border-slate-200 bg-white text-slate-700" type="button" title="Carrito" @click="routeTo('/checkout')">
                        <ShoppingBag :size="19" />
                        <span v-if="cartCount" class="absolute -right-1 -top-1 grid h-5 min-w-5 place-items-center rounded-full bg-violet-600 px-1 text-xs font-bold text-white">{{ cartCount }}</span>
                    </button>
                </div>
                <label class="relative mb-3 block md:hidden">
                    <Search class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400" :size="18" />
                    <input v-model="searchQuery" class="h-11 w-full rounded-xl border border-slate-200 bg-slate-50 pl-11 pr-4 text-sm outline-none" placeholder="Buscar productos...">
                </label>
                <nav class="flex gap-6 overflow-x-auto pb-3 text-sm font-semibold text-slate-600">
                    <button class="shrink-0 text-violet-700" type="button" @click="goHome">Inicio</button>
                    <button v-for="tag in tags.slice(1)" :key="tag" class="shrink-0 hover:text-violet-700" type="button" @click="activeTag = tag; goHome(); scrollTo('featured')">{{ tag }}</button>
                    <button class="shrink-0 text-rose-500" type="button" @click="scrollTo('featured')">Ofertas</button>
                </nav>
            </div>
        </header>

        <p v-if="notice" class="fixed left-4 right-4 top-24 z-50 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 shadow-lg sm:left-auto sm:w-96">{{ notice }}</p>

        <section v-if="view === 'home'">
            <section v-if="heroProduct" class="mx-auto max-w-7xl px-4 py-5 sm:px-6 lg:px-8">
                <div class="relative min-h-[360px] overflow-hidden rounded-2xl bg-slate-950 sm:min-h-[430px]">
                    <img v-if="productImage(heroProduct)" class="absolute inset-0 h-full w-full object-cover opacity-80" :src="productImage(heroProduct)" :alt="heroProduct.name">
                    <div class="absolute inset-0 bg-gradient-to-r from-slate-950 via-slate-950/75 to-transparent"></div>
                    <div class="relative flex min-h-[360px] max-w-2xl flex-col justify-center p-6 text-white sm:min-h-[430px] sm:p-10">
                        <p class="text-xs font-bold uppercase tracking-[0.2em] text-violet-300">{{ storeConfig.heroEyebrow }}</p>
                        <h1 class="mt-3 text-4xl font-black leading-none sm:text-6xl">Nueva colección <span class="text-violet-400">urbana</span></h1>
                        <p class="mt-4 max-w-lg text-sm leading-6 text-slate-200 sm:text-base">Diseño, confort y estilo para todos los días. Descubrí lo último de nuestra tienda.</p>
                        <div class="mt-6 flex flex-wrap gap-3">
                            <button class="h-11 rounded-xl bg-violet-600 px-5 text-sm font-bold text-white" type="button" @click="scrollTo('featured')">Comprar ahora</button>
                            <button class="h-11 rounded-xl border border-white/60 px-5 text-sm font-bold text-white" type="button" @click="scrollTo('featured')">Ver catálogo</button>
                        </div>
                    </div>
                </div>
            </section>

            <section id="featured" class="mx-auto max-w-7xl px-4 pb-10 pt-2 sm:px-6 lg:px-8">
                <div class="mb-4 flex items-end justify-between gap-4">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-violet-600">Catálogo</p>
                        <h2 class="mt-1 text-2xl font-black tracking-tight">Productos destacados</h2>
                    </div>
                    <p class="hidden text-sm text-slate-500 sm:block">Deslizá para seguir viendo más productos</p>
                </div>

                <div v-if="catalogLoading" class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <div v-for="index in 8" :key="index" class="animate-pulse rounded-2xl bg-white p-3 shadow-sm">
                        <div class="aspect-square rounded-xl bg-slate-200"></div>
                        <div class="mt-3 h-3 w-1/3 rounded bg-slate-200"></div>
                        <div class="mt-2 h-4 w-3/4 rounded bg-slate-200"></div>
                        <div class="mt-3 h-10 rounded-xl bg-slate-200"></div>
                    </div>
                </div>

                <div v-else class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
                    <article v-for="product in visibleProducts" :key="product.id" class="group overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg">
                        <button class="relative block w-full text-left" type="button" @click="openProduct(product)">
                            <div class="aspect-square overflow-hidden bg-slate-100">
                                <img v-if="productImage(product)" class="h-full w-full object-cover transition duration-300 group-hover:scale-105" :src="productImage(product)" :alt="product.name">
                                <div v-else class="grid h-full place-items-center text-xs font-semibold text-slate-400">Sin foto</div>
                            </div>
                            <span class="absolute right-3 top-3 grid h-8 w-8 place-items-center rounded-full bg-white/90 text-slate-500 shadow-sm"><Heart :size="16" /></span>
                        </button>
                        <div class="p-3 sm:p-4">
                            <p class="text-[11px] font-bold uppercase tracking-wide text-violet-600">{{ product.category }}</p>
                            <button class="mt-1 block w-full truncate text-left text-sm font-bold sm:text-base" type="button" @click="openProduct(product)">{{ product.name }}</button>
                            <strong class="mt-2 block text-base sm:text-lg">{{ money(product.price) }}</strong>
                            <button class="mt-3 h-10 w-full rounded-xl bg-violet-600 px-3 text-xs font-bold text-white sm:text-sm" type="button" @click="openProduct(product)">Agregar</button>
                        </div>
                    </article>
                </div>

                <div ref="loadMoreTrigger" class="mt-6 min-h-12 text-center">
                    <div v-if="hasMoreProducts" class="inline-flex items-center gap-2 text-sm font-semibold text-slate-500">
                        <span class="h-5 w-5 animate-spin rounded-full border-2 border-violet-200 border-t-violet-600"></span>
                        Cargando más productos...
                    </div>
                </div>
            </section>

            <section v-if="!heroProduct && !catalogLoading" class="mx-auto grid min-h-[50vh] max-w-2xl place-items-center px-4 py-12 text-center">
                <article class="rounded-2xl border border-slate-200 bg-white p-7 shadow-sm">
                    <h1 class="text-2xl font-bold">Todavía no hay productos publicados</h1>
                    <p class="mt-3 text-slate-600">Cuando guardes productos publicados en el admin, van a aparecer acá.</p>
                </article>
            </section>
        </section>

        <section v-else-if="view === 'product' && currentProduct" class="mx-auto grid max-w-7xl gap-6 px-4 py-5 sm:px-6 lg:grid-cols-[minmax(0,1.15fr)_420px] lg:px-8">
            <div>
                <button class="mb-4 inline-flex items-center gap-2 text-sm font-semibold text-slate-600" type="button" @click="goHome"><ArrowLeft :size="17" /> Productos</button>
                <div class="grid gap-3 sm:grid-cols-2">
                    <button v-for="image in currentProduct.images" :key="image.url || image" class="overflow-hidden rounded-2xl bg-slate-100" type="button" @click="activeImage = image.url || image">
                        <img class="aspect-[4/5] h-full w-full object-cover" :src="image.url || image" :alt="currentProduct.name">
                    </button>
                    <div v-if="!currentProduct.images.length" class="grid aspect-[4/5] place-items-center rounded-2xl bg-slate-100 text-sm font-medium text-slate-400">Sin foto</div>
                </div>
            </div>
            <aside class="rounded-2xl bg-white p-5 shadow-sm lg:sticky lg:top-24 lg:self-start">
                <p class="text-xs font-bold uppercase tracking-wide text-violet-600">{{ currentProduct.category }}</p>
                <h1 class="mt-2 text-3xl font-black tracking-tight">{{ currentProduct.name }}</h1>
                <p class="mt-3 text-slate-600">{{ currentProduct.description }}</p>
                <strong class="mt-5 block text-2xl">{{ money(currentProduct.price) }}</strong>
                <div class="mt-5 grid gap-4">
                    <label class="grid gap-2 text-sm font-semibold text-slate-700">
                        Talle
                        <div class="flex flex-wrap gap-2">
                            <button v-for="size in currentProduct.sizes" :key="size" type="button" class="h-10 min-w-12 rounded-xl border px-3 text-sm font-bold" :class="selectedSize === size ? 'border-violet-600 bg-violet-600 text-white' : 'border-slate-300 text-slate-700'" @click="selectedSize = size">{{ size }}</button>
                        </div>
                    </label>
                    <button class="h-12 rounded-xl bg-violet-600 px-5 text-sm font-bold text-white" type="button" @click="addToCart(currentProduct)">Agregar al carrito</button>
                    <button class="h-12 rounded-xl border border-slate-300 px-5 text-sm font-bold text-slate-800" type="button" @click="buyNow(currentProduct)">Comprar ahora</button>
                </div>
            </aside>
        </section>

        <section v-else-if="view === 'checkout'" class="mx-auto grid max-w-7xl gap-5 px-4 py-5 sm:px-6 lg:grid-cols-[minmax(0,1fr)_420px] lg:px-8">
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h1 class="text-2xl font-black">Checkout</h1>
                <div class="mt-5 grid gap-4">
                    <label class="grid gap-2 text-sm font-semibold">Email<input v-model="checkout.email" class="h-11 rounded-xl border border-slate-300 px-3" type="email"></label>
                    <label class="grid gap-2 text-sm font-semibold">Nombre<input v-model="checkout.name" class="h-11 rounded-xl border border-slate-300 px-3"></label>
                    <label class="grid gap-2 text-sm font-semibold">Dirección<input v-model="checkout.address" class="h-11 rounded-xl border border-slate-300 px-3"></label>
                </div>
                <h2 class="mt-6 font-bold">Método de pago</h2>
                <div class="mt-3 grid gap-3">
                    <label v-for="method in paymentMethods" :key="method.id" class="flex items-center justify-between rounded-xl border p-4" :class="checkout.payment === method.id ? 'border-violet-500 bg-violet-50' : 'border-slate-200'">
                        <span><strong class="block text-sm">{{ method.name }}</strong><small class="text-slate-500">{{ method.detail }}</small></span>
                        <input v-model="checkout.payment" type="radio" name="payment" :value="method.id">
                    </label>
                </div>
                <p v-if="checkoutError" class="mt-4 rounded-xl bg-red-50 px-3 py-2 text-sm font-medium text-red-700">{{ checkoutError }}</p>
                <button class="mt-6 h-12 w-full rounded-xl bg-violet-600 px-5 text-sm font-bold text-white disabled:opacity-50" type="button" :disabled="cart.length === 0" @click="placeOrder">{{ cart.length === 0 ? 'Carrito vacío' : 'Confirmar compra' }}</button>
            </article>
            <aside class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="font-bold">Resumen</h2>
                <div class="mt-4 divide-y divide-slate-200">
                    <div v-for="item in cart" :key="item.key" class="flex gap-3 py-3">
                        <div class="h-16 w-14 shrink-0 overflow-hidden rounded-xl bg-slate-100"><img v-if="productImage(item.product)" class="h-full w-full object-cover" :src="productImage(item.product)" :alt="item.product.name"></div>
                        <div class="min-w-0 flex-1"><p class="truncate font-semibold">{{ item.product.name }}</p><p class="text-sm text-slate-500">Talle {{ item.size }}</p></div>
                        <div class="grid justify-items-end gap-2"><strong>{{ money(item.product.price) }}</strong><button class="text-xs font-semibold text-red-600" type="button" @click="removeFromCart(item.key)">Quitar</button></div>
                    </div>
                </div>
                <div class="mt-4 flex justify-between border-t border-slate-300 pt-4"><span>Total</span><strong class="text-xl">{{ money(cartTotal) }}</strong></div>
            </aside>
        </section>

        <section v-else-if="view === 'confirmation'" class="mx-auto grid min-h-[60vh] max-w-2xl place-items-center px-4 py-12 text-center">
            <article class="rounded-2xl bg-white p-8 shadow-sm"><span class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-emerald-50 text-emerald-700"><Check :size="28" /></span><h1 class="mt-4 text-3xl font-black">Compra confirmada</h1><p class="mt-3 text-slate-600">Tu orden {{ orderNumber }} fue creada.</p><button class="mt-6 h-11 rounded-xl bg-violet-600 px-5 text-sm font-bold text-white" type="button" @click="goHome">Volver a la tienda</button></article>
        </section>

        <div class="fixed bottom-24 right-4 z-40 grid gap-2 sm:bottom-6 sm:right-6">
            <a v-if="contacts.instagram" :href="contacts.instagram" target="_blank" rel="noopener" class="grid h-11 w-11 place-items-center rounded-full bg-white text-xs font-black text-pink-600 shadow-lg" aria-label="Instagram">IG</a>
            <a v-if="contacts.facebook" :href="contacts.facebook" target="_blank" rel="noopener" class="grid h-11 w-11 place-items-center rounded-full bg-white text-xs font-black text-blue-600 shadow-lg" aria-label="Facebook">FB</a>
            <a v-if="contacts.tiktok" :href="contacts.tiktok" target="_blank" rel="noopener" class="grid h-11 w-11 place-items-center rounded-full bg-white text-xs font-black text-slate-950 shadow-lg" aria-label="TikTok">TT</a>
            <a v-if="contacts.youtube" :href="contacts.youtube" target="_blank" rel="noopener" class="grid h-11 w-11 place-items-center rounded-full bg-white text-xs font-black text-red-600 shadow-lg" aria-label="YouTube">YT</a>
            <a v-if="contacts.whatsapp" :href="whatsappUrl" target="_blank" rel="noopener" class="mt-2 grid h-14 w-14 place-items-center rounded-full bg-emerald-500 text-white shadow-xl" aria-label="WhatsApp"><MessageCircle :size="28" /></a>
        </div>
    </main>
</template>

<script setup>
import { ArrowLeft, Check, Heart, Menu, MessageCircle, Search, ShoppingBag, User } from '@lucide/vue';
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';

const storeKey = window.__STORE_KEY__ || 'demo';
const contacts = window.__STOREFRONT_CONTACTS__ || {};
const storeConfig = { name: storeKey === 'demo' ? 'Ecom Store' : `${storeKey} Store`, heroEyebrow: 'Nueva temporada', currency: 'USD' };
const products = ref([]);
const searchQuery = ref('');
const activeTag = ref('Todos');
const visibleCount = ref(8);
const loadMoreTrigger = ref(null);
const mobileMenu = ref(false);
let observer = null;

const paymentMethods = [
    { id: 'card', name: 'Tarjeta', detail: 'Pago online' },
    { id: 'transfer', name: 'Transferencia', detail: 'Confirmación manual' },
    { id: 'cash', name: 'Efectivo', detail: 'Pago al retirar' },
];
const view = ref(resolveView());
const currentProduct = ref(null);
const activeImage = ref('');
const selectedSize = ref('Unico');
const cart = ref(loadCart());
const orderNumber = ref('');
const notice = ref('');
const checkoutError = ref('');
const catalogLoading = ref(false);
const checkout = reactive({ email: '', name: '', address: '', payment: 'card' });

const heroProduct = computed(() => filteredProducts.value[0] || products.value[0] || null);
const storeInitials = computed(() => storeConfig.name.split(' ').map((part) => part[0]).join('').slice(0, 2).toUpperCase());
const cartCount = computed(() => cart.value.length);
const cartTotal = computed(() => cart.value.reduce((total, item) => total + Number(item.product.price || 0), 0));
const tags = computed(() => ['Todos', ...new Set(products.value.map((product) => product.category).filter(Boolean))]);
const filteredProducts = computed(() => products.value.filter((product) => {
    const matchesTag = activeTag.value === 'Todos' || product.category === activeTag.value;
    const query = searchQuery.value.trim().toLowerCase();
    const matchesSearch = !query || [product.name, product.sku, product.category, product.description].some((value) => String(value || '').toLowerCase().includes(query));
    return matchesTag && matchesSearch;
}));
const visibleProducts = computed(() => filteredProducts.value.slice(0, visibleCount.value));
const hasMoreProducts = computed(() => visibleCount.value < filteredProducts.value.length);
const whatsappUrl = computed(() => contacts.whatsapp ? `https://wa.me/${String(contacts.whatsapp).replace(/\D/g, '')}?text=${encodeURIComponent(contacts.whatsappMessage || 'Hola, quiero consultar por un producto.')}` : '#');

watch([searchQuery, activeTag], () => { visibleCount.value = 8; });

function setupObserver() {
    observer?.disconnect();
    observer = new IntersectionObserver((entries) => {
        if (entries[0]?.isIntersecting && hasMoreProducts.value) visibleCount.value += 8;
    }, { rootMargin: '240px' });
    if (loadMoreTrigger.value) observer.observe(loadMoreTrigger.value);
}

function routeTo(path) {
    window.history.pushState({}, '', `/t/${storeKey}${path}`);
    view.value = resolveView();
    currentProduct.value = resolveProduct();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
function goHome() { routeTo(''); }
function openProduct(product) { currentProduct.value = product; activeImage.value = productImage(product); selectedSize.value = product.sizes[0] || 'Unico'; routeTo(`/product/${product.id}`); }
function scrollTo(id) { document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
function addToCart(product) {
    if (!product) return;
    cart.value.push({ key: `${product.id}-${selectedSize.value}-${Date.now()}`, product, size: selectedSize.value });
    persistCart();
    showNotice('Producto agregado al carrito.');
}
function buyNow(product) { addToCart(product); if (product) routeTo('/checkout'); }
function removeFromCart(key) { cart.value = cart.value.filter((item) => item.key !== key); persistCart(); }

async function placeOrder() {
    if (!cart.value.length) { checkoutError.value = 'Agrega un producto al carrito antes de confirmar.'; return; }
    if (!checkout.email || !checkout.name || !checkout.address) { checkoutError.value = 'Completa email, nombre y dirección.'; return; }
    checkoutError.value = '';
    const response = await fetch(`/api/store/${storeKey}/orders`, { method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify({ currency: storeConfig.currency, customer: checkout, items: cart.value.map((item) => ({ id: item.product.id, sku: item.product.sku, name: item.product.name, vendor: item.product.vendor, description: item.product.description, image: productImage(item.product), price: item.product.price, quantity: 1 })) }) });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) { checkoutError.value = payload.message || 'No se pudo guardar la orden.'; return; }
    orderNumber.value = payload.data?.order_number || '';
    cart.value = [];
    persistCart();
    routeTo(`/confirmation/${orderNumber.value}`);
}

function resolveView() {
    const path = window.location.pathname;
    if (path.includes('/checkout')) return 'checkout';
    if (path.includes('/confirmation/')) return 'confirmation';
    if (path.includes('/product/')) return 'product';
    return 'home';
}
function resolveProduct() {
    const id = window.location.pathname.match(/\/product\/([^/]+)/)?.[1];
    return products.value.find((product) => product.id === id || product.sku === id) || null;
}
function money(value) { return new Intl.NumberFormat('es-AR', { style: 'currency', currency: storeConfig.currency }).format(value); }
function productImage(product) { const image = product?.images?.[0]; return typeof image === 'string' ? image : image?.url || ''; }
function loadCart() { try { const stored = JSON.parse(sessionStorage.getItem(cartStorageKey()) || '[]'); return Array.isArray(stored) ? stored : []; } catch { return []; } }
function persistCart() { sessionStorage.setItem(cartStorageKey(), JSON.stringify(cart.value)); }
function cartStorageKey() { return `storefront_cart_${storeKey}`; }
function showNotice(message) { notice.value = message; window.clearTimeout(showNotice.timeout); showNotice.timeout = window.setTimeout(() => { notice.value = ''; }, 2200); }

async function loadProducts() {
    catalogLoading.value = true;
    try {
        const response = await fetch(`/api/store/${storeKey}/products`, { headers: { Accept: 'application/json' } });
        const payload = await response.json().catch(() => ({ data: [] }));
        products.value = (payload.data || []).map((product) => ({ ...product, price: Number(product.price || 0), images: product.images || [], sizes: product.sizes?.length ? product.sizes : ['Unico'] }));
        currentProduct.value = resolveProduct();
        if (currentProduct.value) selectedSize.value = currentProduct.value.sizes[0] || 'Unico';
    } catch { products.value = []; currentProduct.value = null; }
    finally { catalogLoading.value = false; await nextTick(); setupObserver(); }
}

window.addEventListener('popstate', () => { view.value = resolveView(); currentProduct.value = resolveProduct(); });
onMounted(loadProducts);
onBeforeUnmount(() => observer?.disconnect());
</script>
