<template>
    <main class="min-h-screen bg-white text-slate-950">
        <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur">
            <div class="mx-auto flex h-14 max-w-7xl items-center justify-between gap-3 px-4 sm:h-16 sm:px-6 lg:px-8">
                <button class="flex items-center gap-2 text-left" type="button" @click="goHome">
                    <span class="grid h-9 w-9 place-items-center rounded-md bg-slate-950 text-xs font-black text-white">{{ storeInitials }}</span>
                    <span>
                        <strong class="block text-sm font-semibold leading-4">{{ storeConfig.name }}</strong>
                        <small class="text-xs text-slate-500">{{ storeKey }}</small>
                    </span>
                </button>

                <nav class="hidden items-center gap-6 text-sm font-medium text-slate-600 md:flex">
                    <button type="button" class="hover:text-slate-950" @click="scrollTo('featured')">Productos</button>
                    <button type="button" class="hover:text-slate-950" @click="scrollTo('collections')">Colecciones</button>
                    <button type="button" class="hover:text-slate-950" @click="routeTo('/checkout')">Checkout</button>
                </nav>

                <button class="relative grid h-10 w-10 place-items-center rounded-md border border-slate-300 text-slate-700" type="button" title="Carrito" @click="routeTo('/checkout')">
                    <ShoppingBag :size="19" />
                    <span class="absolute -right-1 -top-1 grid h-5 min-w-5 place-items-center rounded-full bg-emerald-600 px-1 text-xs font-semibold text-white">{{ cartCount }}</span>
                </button>
            </div>
        </header>
        <p v-if="notice" class="fixed left-4 right-4 top-16 z-40 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 shadow-sm sm:left-auto sm:w-96">
            {{ notice }}
        </p>

        <section v-if="view === 'home'">
            <section v-if="heroProduct" class="mx-auto grid max-w-7xl gap-5 px-4 py-5 sm:px-6 lg:grid-cols-[1.15fr_0.85fr] lg:px-8 lg:py-8">
                <div class="relative min-h-[480px] overflow-hidden rounded-lg bg-slate-950 sm:min-h-[560px]">
                    <img v-if="productImage(heroProduct)" class="absolute inset-0 h-full w-full object-cover opacity-85" :src="productImage(heroProduct)" :alt="heroProduct.name">
                    <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-slate-950 via-slate-950/70 to-transparent p-5 text-white sm:p-8">
                        <p class="text-xs font-semibold uppercase tracking-wide text-emerald-300">{{ storeConfig.heroEyebrow }}</p>
                        <h1 class="mt-2 max-w-xl text-4xl font-semibold tracking-normal sm:text-5xl">{{ storeConfig.heroTitle }}</h1>
                        <p class="mt-3 max-w-lg text-sm leading-6 text-slate-200 sm:text-base">{{ storeConfig.heroCopy }}</p>
                        <div class="mt-5 flex flex-wrap gap-2">
                            <button class="inline-flex h-11 items-center justify-center rounded-md bg-white px-5 text-sm font-semibold text-slate-950" type="button" @click="openProduct(heroProduct)">
                                Ver producto
                            </button>
                            <button class="inline-flex h-11 items-center justify-center rounded-md border border-white/50 px-5 text-sm font-semibold text-white" type="button" @click="scrollTo('featured')">
                                Explorar tienda
                            </button>
                        </div>
                    </div>
                </div>

                <div id="collections" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-1">
                    <article v-for="collection in collections" :key="collection.name" class="relative min-h-52 overflow-hidden rounded-lg bg-slate-100">
                        <img v-if="collection.image" class="absolute inset-0 h-full w-full object-cover" :src="collection.image" :alt="collection.name">
                        <button class="absolute inset-0 flex items-end bg-slate-950/25 p-4 text-left text-white" type="button" @click="filterCollection(collection.name)">
                            <span>
                                <strong class="block text-xl font-semibold">{{ collection.name }}</strong>
                                <small class="text-sm text-white/85">{{ collection.copy }}</small>
                            </span>
                        </button>
                    </article>
                </div>
            </section>

            <section class="border-y border-slate-200 bg-slate-50">
                <div class="mx-auto flex max-w-7xl gap-3 overflow-x-auto px-4 py-3 sm:px-6 lg:px-8">
                    <button v-for="tag in tags" :key="tag" type="button" class="h-10 shrink-0 rounded-md border px-4 text-sm font-medium" :class="activeTag === tag ? 'border-slate-950 bg-slate-950 text-white' : 'border-slate-300 bg-white text-slate-700'" @click="activeTag = tag">
                        {{ tag }}
                    </button>
                </div>
            </section>

            <section id="featured" class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                <div class="flex items-end justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Catalogo</p>
                        <h2 class="mt-1 text-2xl font-semibold tracking-normal">Productos destacados</h2>
                    </div>
                    <p class="text-sm text-slate-500">{{ filteredProducts.length }} productos</p>
                </div>

                <div class="mt-4 flex snap-x gap-4 overflow-x-auto pb-2 lg:grid lg:grid-cols-4 lg:overflow-visible">
                    <article v-for="product in filteredProducts" :key="product.id" class="w-[78vw] shrink-0 snap-start overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm sm:w-72 lg:w-auto">
                        <button class="block w-full text-left" type="button" @click="openProduct(product)">
                            <div class="aspect-[4/5] overflow-hidden bg-slate-100">
                                <img v-if="productImage(product)" class="h-full w-full object-cover transition duration-300 hover:scale-105" :src="productImage(product)" :alt="product.name">
                                <div v-else class="grid h-full place-items-center text-sm font-medium text-slate-400">Sin foto</div>
                            </div>
                            <div class="p-4">
                                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">{{ product.category }}</p>
                                <h3 class="mt-1 font-semibold text-slate-950">{{ product.name }}</h3>
                                <p class="mt-2 text-sm text-slate-500">{{ product.summary }}</p>
                                <div class="mt-4 flex items-center justify-between">
                                    <strong>{{ money(product.price) }}</strong>
                                    <span class="rounded-md bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-700">Disponible</span>
                                </div>
                            </div>
                        </button>
                    </article>
                </div>
            </section>
            <section v-if="!heroProduct" class="mx-auto grid min-h-[calc(100vh-64px)] max-w-2xl place-items-center px-4 py-12 text-center">
                <article class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                    <h1 class="text-2xl font-semibold tracking-normal">Todavia no hay productos publicados</h1>
                    <p class="mt-3 text-slate-600">Cuando guardes productos publicados en el admin, van a aparecer aca desde la API publica.</p>
                </article>
            </section>
        </section>

        <section v-else-if="view === 'product' && currentProduct" class="mx-auto grid max-w-7xl gap-6 px-4 py-5 sm:px-6 lg:grid-cols-[minmax(0,1.15fr)_420px] lg:px-8">
            <div>
                <button class="mb-4 inline-flex items-center gap-2 text-sm font-medium text-slate-600" type="button" @click="goHome">
                    <ArrowLeft :size="17" />
                    Productos
                </button>
                <div class="grid gap-3 sm:grid-cols-2">
                    <button v-for="image in currentProduct.images" :key="image.url || image" class="overflow-hidden rounded-lg bg-slate-100" type="button" @click="activeImage = image.url || image">
                        <img class="aspect-[4/5] h-full w-full object-cover" :src="image.url || image" :alt="currentProduct.name">
                    </button>
                    <div v-if="!currentProduct.images.length" class="grid aspect-[4/5] place-items-center rounded-lg bg-slate-100 text-sm font-medium text-slate-400">Sin foto</div>
                </div>
            </div>

            <aside class="lg:sticky lg:top-20 lg:self-start">
                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">{{ currentProduct.category }}</p>
                <h1 class="mt-2 text-3xl font-semibold tracking-normal">{{ currentProduct.name }}</h1>
                <p class="mt-3 text-slate-600">{{ currentProduct.description }}</p>
                <strong class="mt-5 block text-2xl">{{ money(currentProduct.price) }}</strong>

                <div class="mt-5 grid gap-4">
                    <label class="grid gap-2 text-sm font-medium text-slate-700">
                        Color
                        <select id="product-color" v-model="selectedColor" name="product_color" class="h-11 rounded-md border border-slate-300 px-3">
                            <option v-for="color in currentProduct.colors" :key="color">{{ color }}</option>
                        </select>
                    </label>
                    <label class="grid gap-2 text-sm font-medium text-slate-700">
                        Talle
                        <div class="flex flex-wrap gap-2">
                            <button v-for="size in currentProduct.sizes" :key="size" type="button" class="h-10 min-w-12 rounded-md border px-3 text-sm font-medium" :class="selectedSize === size ? 'border-slate-950 bg-slate-950 text-white' : 'border-slate-300 text-slate-700'" @click="selectedSize = size">
                                {{ size }}
                            </button>
                        </div>
                    </label>
                    <button class="h-12 rounded-md bg-emerald-600 px-5 text-sm font-semibold text-white" type="button" @click="addToCart(currentProduct)">
                        Agregar al carrito
                    </button>
                    <button class="h-12 rounded-md border border-slate-300 px-5 text-sm font-semibold text-slate-800" type="button" @click="buyNow(currentProduct)">
                        Comprar ahora
                    </button>
                </div>

                <dl class="mt-6 grid gap-3 border-t border-slate-200 pt-5 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-500">SKU</dt>
                        <dd class="font-medium">{{ currentProduct.sku }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-500">Tenant</dt>
                        <dd class="font-medium">{{ storeKey }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-500">API destino</dt>
                        <dd class="font-medium">Aimeos JSON API</dd>
                    </div>
                </dl>
            </aside>
        </section>
        <section v-else-if="view === 'product'" class="mx-auto grid min-h-[calc(100vh-64px)] max-w-2xl place-items-center px-4 py-12 text-center">
            <article class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                <h1 class="text-2xl font-semibold tracking-normal">{{ catalogLoading ? 'Cargando producto' : 'Producto no encontrado' }}</h1>
                <p class="mt-3 text-slate-600">{{ catalogLoading ? 'Estamos cargando el catalogo de la tienda.' : 'El producto no esta publicado o no existe en esta tienda.' }}</p>
                <button v-if="!catalogLoading" class="mt-6 h-11 rounded-md bg-slate-950 px-5 text-sm font-semibold text-white" type="button" @click="goHome">
                    Volver a la tienda
                </button>
            </article>
        </section>

        <section v-else-if="view === 'checkout'" class="mx-auto grid max-w-7xl gap-5 px-4 py-5 sm:px-6 lg:grid-cols-[minmax(0,1fr)_420px] lg:px-8">
            <article class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                <h1 class="text-2xl font-semibold tracking-normal">Checkout</h1>
                <div class="mt-5 grid gap-4">
                    <label class="grid gap-2 text-sm font-medium text-slate-700">
                        Email
                        <input id="checkout-email" v-model="checkout.email" name="email" class="h-11 rounded-md border border-slate-300 px-3" type="email" placeholder="cliente@email.com">
                    </label>
                    <label class="grid gap-2 text-sm font-medium text-slate-700">
                        Nombre
                        <input id="checkout-name" v-model="checkout.name" name="name" class="h-11 rounded-md border border-slate-300 px-3" placeholder="Nombre completo">
                    </label>
                    <label class="grid gap-2 text-sm font-medium text-slate-700">
                        Direccion
                        <input id="checkout-address" v-model="checkout.address" name="address" class="h-11 rounded-md border border-slate-300 px-3" placeholder="Calle, numero, ciudad">
                    </label>
                </div>

                <h2 class="mt-6 text-base font-semibold">Metodo de pago</h2>
                <div class="mt-3 grid gap-3">
                    <label v-for="method in paymentMethods" :key="method.id" class="flex items-center justify-between gap-3 rounded-lg border p-4" :class="checkout.payment === method.id ? 'border-emerald-600 bg-emerald-50' : 'border-slate-200 bg-white'">
                        <span>
                            <strong class="block text-sm">{{ method.name }}</strong>
                            <small class="text-slate-500">{{ method.detail }}</small>
                        </span>
                        <input v-model="checkout.payment" type="radio" name="payment" :value="method.id">
                    </label>
                </div>

                <p v-if="checkoutError" class="mt-4 rounded-md bg-red-50 px-3 py-2 text-sm font-medium text-red-700">{{ checkoutError }}</p>
                <button class="mt-6 h-12 w-full rounded-md bg-slate-950 px-5 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-60" type="button" :disabled="cart.length === 0" @click="placeOrder">
                    {{ cart.length === 0 ? 'Carrito vacio' : 'Confirmar compra' }}
                </button>
                <p class="mt-3 text-xs text-slate-500">Checkout preparado para enviar basket/order/service usando el tenant {{ storeKey }}.</p>
            </article>

            <aside class="rounded-lg border border-slate-200 bg-slate-50 p-4 sm:p-5">
                <h2 class="text-base font-semibold">Resumen</h2>
                <div v-if="cart.length === 0" class="mt-4 rounded-md border border-dashed border-slate-300 bg-white p-4 text-sm text-slate-500">
                    Tu carrito esta vacio.
                </div>
                <div class="mt-4 divide-y divide-slate-200">
                    <div v-for="item in cart" :key="item.key" class="flex gap-3 py-3">
                        <div class="h-16 w-14 shrink-0 overflow-hidden rounded-md bg-slate-200">
                            <img v-if="productImage(item.product)" class="h-full w-full object-cover" :src="productImage(item.product)" :alt="item.product.name">
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="font-medium">{{ item.product.name }}</p>
                            <p class="text-sm text-slate-500">{{ item.color }} · {{ item.size }}</p>
                        </div>
                        <div class="grid justify-items-end gap-2">
                            <strong>{{ money(item.product.price) }}</strong>
                            <button class="text-xs font-medium text-red-600" type="button" @click="removeFromCart(item.key)">Quitar</button>
                        </div>
                    </div>
                </div>
                <div class="mt-4 flex items-center justify-between border-t border-slate-300 pt-4">
                    <span>Total</span>
                    <strong class="text-xl">{{ money(cartTotal) }}</strong>
                </div>
            </aside>
        </section>

        <section v-else-if="view === 'confirmation'" class="mx-auto grid min-h-[calc(100vh-64px)] max-w-2xl place-items-center px-4 py-12 text-center">
            <article class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                <span class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-emerald-50 text-emerald-700">
                    <Check :size="28" />
                </span>
                <h1 class="mt-4 text-3xl font-semibold tracking-normal">Compra confirmada</h1>
                <p class="mt-3 text-slate-600">Tu orden {{ orderNumber }} fue creada para la tienda {{ storeConfig.name }}.</p>
                <button class="mt-6 h-11 rounded-md bg-slate-950 px-5 text-sm font-semibold text-white" type="button" @click="goHome">
                    Volver a la tienda
                </button>
            </article>
        </section>
        <section v-else class="mx-auto grid min-h-[calc(100vh-64px)] max-w-2xl place-items-center px-4 py-12 text-center">
            <article class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                <h1 class="text-2xl font-semibold tracking-normal">Vista no disponible</h1>
                <button class="mt-6 h-11 rounded-md bg-slate-950 px-5 text-sm font-semibold text-white" type="button" @click="goHome">
                    Volver a la tienda
                </button>
            </article>
        </section>
    </main>
</template>

<script setup>
import { ArrowLeft, Check, ShoppingBag } from '@lucide/vue';
import { computed, onMounted, reactive, ref } from 'vue';

const storeKey = window.__STORE_KEY__ || 'demo';

const storeConfig = {
    name: storeKey === 'demo' ? 'Demo Store' : `${storeKey} Store`,
    heroEyebrow: 'Nueva temporada',
    heroTitle: 'Productos listos para vender con Aimeos headless',
    heroCopy: 'Una experiencia propia para cada tenant, preparada para consumir catalogo, carrito, pagos y ordenes desde Aimeos.',
    currency: 'USD',
};

const products = ref([]);
const collections = computed(() => [...new Set(products.value.map((product) => product.category))].slice(0, 3).map((name) => {
    const product = products.value.find((item) => item.category === name);

    return { name, copy: `${products.value.filter((item) => item.category === name).length} productos`, image: productImage(product) };
}));
const tags = computed(() => ['Todos', ...new Set(products.value.map((product) => product.category))]);
const paymentMethods = [
    { id: 'card', name: 'Tarjeta', detail: 'Pago online via service Aimeos' },
    { id: 'transfer', name: 'Transferencia', detail: 'Confirmacion manual' },
    { id: 'cash', name: 'Efectivo', detail: 'Pago al retirar' },
];

const view = ref(resolveView());
const activeTag = ref('Todos');
const currentProduct = ref(resolveProduct());
const activeImage = ref('');
const selectedColor = ref('Unico');
const selectedSize = ref('Unico');
const cart = ref(loadCart());
const orderNumber = ref('');
const notice = ref('');
const checkoutError = ref('');
const catalogLoading = ref(false);
const checkout = reactive({
    email: '',
    name: '',
    address: '',
    payment: 'card',
});

const heroProduct = computed(() => products.value[0] || null);
const storeInitials = computed(() => storeConfig.name.split(' ').map((part) => part[0]).join('').slice(0, 2).toUpperCase());
const cartCount = computed(() => cart.value.length);
const cartTotal = computed(() => cart.value.reduce((total, item) => total + Number(item.product.price || 0), 0));
const filteredProducts = computed(() => activeTag.value === 'Todos' ? products.value : products.value.filter((product) => product.category === activeTag.value));

function routeTo(path) {
    window.history.pushState({}, '', `/t/${storeKey}${path}`);
    view.value = resolveView();
    currentProduct.value = resolveProduct();
}

function goHome() {
    routeTo('');
}

function openProduct(product) {
    currentProduct.value = product;
    activeImage.value = productImage(product);
    selectedColor.value = product.colors[0] || 'Unico';
    selectedSize.value = product.sizes[0] || 'Unico';
    routeTo(`/product/${product.id}`);
}

function filterCollection(category) {
    activeTag.value = category;
    scrollTo('featured');
}

function scrollTo(id) {
    document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function addToCart(product) {
    if (!product) {
        checkoutError.value = 'Selecciona un producto antes de agregar al carrito.';
        return;
    }

    cart.value.push({
        key: `${product.id}-${selectedColor.value}-${selectedSize.value}-${Date.now()}`,
        product,
        color: selectedColor.value,
        size: selectedSize.value,
    });
    persistCart();
    checkoutError.value = '';
    showNotice('Producto agregado al carrito.');
}

function buyNow(product) {
    addToCart(product);

    if (product) {
        routeTo('/checkout');
    }
}

function removeFromCart(key) {
    cart.value = cart.value.filter((item) => item.key !== key);
    persistCart();
}

async function placeOrder() {
    if (cart.value.length === 0) {
        checkoutError.value = 'Agrega un producto al carrito antes de confirmar.';
        return;
    }

    if (!checkout.email || !checkout.name || !checkout.address) {
        checkoutError.value = 'Completa email, nombre y direccion para confirmar la compra.';
        return;
    }

    checkoutError.value = '';

    const response = await fetch(`/api/store/${storeKey}/orders`, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            currency: storeConfig.currency,
            customer: checkout,
            items: cart.value.map((item) => ({
                id: item.product.id,
                sku: item.product.sku,
                name: item.product.name,
                vendor: item.product.vendor,
                description: item.product.description,
                image: productImage(item.product),
                price: item.product.price,
                quantity: 1,
            })),
        }),
    });

    const payload = await response.json().catch(() => ({}));

    if (!response.ok) {
        checkoutError.value = payload.message || 'No se pudo guardar la orden.';
        return;
    }

    orderNumber.value = payload.data?.order_number || '';
    cart.value = [];
    persistCart();
    routeTo(`/confirmation/${orderNumber.value}`);
}

function resolveView() {
    const path = window.location.pathname;

    if (path.includes('/checkout')) {
        return 'checkout';
    }

    if (path.includes('/confirmation/')) {
        return 'confirmation';
    }

    if (path.includes('/product/')) {
        return 'product';
    }

    return 'home';
}

function resolveProduct() {
    const id = window.location.pathname.match(/\/product\/([^/]+)/)?.[1];

    return products.value.find((product) => product.id === id || product.sku === id) || products.value[0] || null;
}

function money(value) {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: storeConfig.currency,
    }).format(value);
}

function productImage(product) {
    if (!product?.images?.length) {
        return '';
    }

    const image = product.images[0];

    return typeof image === 'string' ? image : image.url;
}

function loadCart() {
    try {
        const stored = JSON.parse(sessionStorage.getItem(cartStorageKey()) || '[]');

        return Array.isArray(stored) ? stored : [];
    } catch {
        sessionStorage.removeItem(cartStorageKey());
        return [];
    }
}

function persistCart() {
    sessionStorage.setItem(cartStorageKey(), JSON.stringify(cart.value));
}

function cartStorageKey() {
    return `storefront_cart_${storeKey}`;
}

function showNotice(message) {
    notice.value = message;
    window.clearTimeout(showNotice.timeout);
    showNotice.timeout = window.setTimeout(() => {
        notice.value = '';
    }, 2200);
}

async function loadProducts() {
    catalogLoading.value = true;

    try {
        const response = await fetch(`/api/store/${storeKey}/products`, {
            headers: { Accept: 'application/json' },
        });
        const payload = await response.json().catch(() => ({ data: [] }));

        products.value = (payload.data || []).map((product) => ({
            ...product,
            price: Number(product.price || 0),
            images: product.images || [],
            colors: product.colors?.length ? product.colors : ['Unico'],
            sizes: product.sizes?.length ? product.sizes : ['Unico'],
        }));
        currentProduct.value = resolveProduct();
        cart.value = cart.value
            .map((item) => {
                const freshProduct = products.value.find((product) => product.id === item.product?.id || product.sku === item.product?.sku);

                return freshProduct ? { ...item, product: freshProduct } : item;
            })
            .filter((item) => item.product);
        persistCart();

        if (currentProduct.value) {
            activeImage.value = productImage(currentProduct.value);
            selectedColor.value = currentProduct.value.colors[0] || 'Unico';
            selectedSize.value = currentProduct.value.sizes[0] || 'Unico';
        }
    } catch {
        products.value = [];
        currentProduct.value = null;
    } finally {
        catalogLoading.value = false;
    }
}

window.addEventListener('popstate', () => {
    view.value = resolveView();
    currentProduct.value = resolveProduct();
});

onMounted(loadProducts);
</script>
