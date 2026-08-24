<template>
    <main class="min-h-screen bg-slate-100 text-slate-950">
        <aside class="fixed inset-y-0 left-0 z-30 hidden w-64 border-r border-slate-200 bg-white lg:flex lg:flex-col">
            <div class="flex h-16 items-center gap-3 border-b border-slate-200 px-4">
                <span class="grid h-9 w-9 place-items-center rounded-md bg-emerald-600 text-sm font-black text-white">EC</span>
                <div>
                    <strong class="block text-sm font-semibold leading-5">Ecom Manage</strong>
                    <small class="text-xs text-slate-500">Admin custom</small>
                </div>
            </div>

            <nav class="flex-1 space-y-1 px-3 py-4" aria-label="Admin">
                <button
                    v-for="item in navItems"
                    :key="item.key"
                    type="button"
                    :class="[ui.navButton, isNavActive(item.key) ? ui.navButtonActive : ui.navButtonIdle]"
                    @click="openSection(item.key)"
                >
                    <component :is="item.icon" :size="18" />
                    <span>{{ item.label }}</span>
                </button>
            </nav>

            <div class="border-t border-slate-200 p-4">
                <p class="truncate text-sm font-medium text-slate-900">{{ userLabel }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ sessionLabel }}</p>
            </div>
        </aside>

        <section class="lg:pl-64">
            <header class="sticky top-0 z-20 border-b border-slate-200 bg-white/95 px-4 py-3 backdrop-blur sm:px-5 lg:px-8">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">{{ activeItem.label }}</p>
                        <h1 class="text-xl font-semibold tracking-normal text-slate-950 sm:text-2xl">{{ activeItem.title }}</h1>
                    </div>

                    <div class="flex gap-2">
                        <button type="button" :class="ui.iconButton" title="Actualizar sesion" @click="loadUser">
                            <RefreshCw :size="18" />
                        </button>
                        <button type="button" :class="ui.primaryButton" @click="primaryAction">
                            <Plus :size="18" />
                            <span>{{ activeItem.action }}</span>
                        </button>
                    </div>
                </div>

                <div class="mt-3 flex gap-2 overflow-x-auto lg:hidden">
                    <button
                        v-for="item in navItems"
                        :key="item.key"
                        type="button"
                        :class="[ui.mobileTab, isNavActive(item.key) ? ui.mobileTabActive : ui.mobileTabIdle]"
                        @click="openSection(item.key)"
                    >
                        <component :is="item.icon" :size="16" />
                        <span>{{ item.label }}</span>
                    </button>
                </div>
            </header>

            <div class="mx-auto max-w-7xl space-y-4 px-4 py-4 sm:px-5 lg:px-8">
                <section v-if="activeSection === 'product-form'" class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_360px]">
                    <article :class="[ui.card, 'p-4 sm:p-5']">
                        <div class="flex flex-col gap-3 border-b border-slate-200 pb-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <button type="button" class="mb-3 inline-flex items-center gap-2 text-sm font-medium text-slate-600 hover:text-slate-950" @click="openSection('products')">
                                    <ArrowLeft :size="17" />
                                    <span>Productos</span>
                                </button>
                                <h2 class="text-lg font-semibold">{{ productFormTitle }}</h2>
                                <p class="mt-1 text-sm text-slate-500">Solo nombre y precio final son obligatorios.</p>
                                <p v-if="productNotice" class="mt-3 rounded-md bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-700">{{ productNotice }}</p>
                                <div v-if="Object.keys(productErrors).length" class="mt-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">
                                    <p v-for="error in productErrors" :key="error">{{ error }}</p>
                                </div>
                            </div>
                            <status-pill :value="productDraft.status" />
                        </div>

                        <form class="mt-5 grid gap-5" @submit.prevent>
                            <section class="grid gap-4">
                                <h3 class="text-sm font-semibold text-slate-900">Informacion principal</h3>
                                <label :class="ui.label">
                                    Nombre *
                                    <input id="product-name" v-model="productDraft.name" name="product_name" required :class="ui.input">
                                </label>
                                <label :class="ui.label">
                                    Descripcion
                                    <textarea id="product-description" v-model="productDraft.description" name="product_description" :class="[ui.input, 'min-h-28 py-2']" />
                                </label>
                                <label :class="ui.label">
                                    Precio final *
                                    <input id="product-final-price" v-model="productDraft.price" name="product_final_price" required inputmode="decimal" :class="ui.input">
                                </label>
                            </section>

                            <section class="grid gap-3">
                                <div>
                                    <h3 class="text-sm font-semibold text-slate-900">Fotos</h3>
                                    <p class="mt-1 text-sm text-slate-500">Subida multiple desde celular. Las imagenes se comprimen a JPG 1024px antes de enviarse.</p>
                                </div>
                                <file-pond
                                    ref="pond"
                                    name="file"
                                    label-idle="Arrastra fotos o hace click"
                                    accepted-file-types="image/png,image/jpeg"
                                    :allow-multiple="true"
                                    :allow-revert="false"
                                    :chunk-uploads="false"
                                    :allow-image-resize="true"
                                    :allow-image-transform="true"
                                    :image-resize-target-width="1024"
                                    :image-resize-target-height="1024"
                                    image-resize-mode="contain"
                                    image-transform-output-mime-type="image/jpeg"
                                    :image-transform-output-quality="80"
                                    :server="pondServer"
                                    :files="productFiles"
                                    @updatefiles="productFiles = $event"
                                    @processfile="handleFileUpload"
                                    @removefile="handleFileRemove"
                                />
                                <div v-if="uploadedImages.length" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                    <article
                                        v-for="(image, index) in uploadedImages"
                                        :key="image.id || image.url"
                                        class="overflow-hidden rounded-lg border bg-white"
                                        :class="index === 0 ? 'border-emerald-500 ring-2 ring-emerald-100' : 'border-slate-200'"
                                    >
                                        <img class="aspect-square w-full object-cover" :src="image.url" :alt="image.id || `Foto ${index + 1}`">
                                        <div class="grid gap-2 p-3">
                                            <div class="flex items-center justify-between gap-2">
                                                <span class="truncate text-xs font-medium text-slate-500">{{ image.id || image.url }}</span>
                                                <span v-if="index === 0" class="rounded-md bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-700">Principal</span>
                                            </div>
                                            <div class="grid grid-cols-2 gap-2">
                                                <button type="button" :class="ui.secondaryButton" :disabled="index === 0" @click="makePrimaryImage(index)">
                                                    <Star :size="16" />
                                                    <span>Principal</span>
                                                </button>
                                                <button type="button" class="inline-flex h-10 items-center justify-center gap-2 rounded-md border border-red-200 bg-white px-3 text-sm font-medium text-red-700 hover:bg-red-50" @click="removeUploadedImage(index)">
                                                    <Trash2 :size="16" />
                                                    <span>Borrar</span>
                                                </button>
                                            </div>
                                        </div>
                                    </article>
                                </div>
                            </section>

                            <section class="grid gap-3">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <h3 class="text-sm font-semibold text-slate-900">Variantes</h3>
                                        <p class="mt-1 text-sm text-slate-500">Color y talle para generar combinaciones vendibles.</p>
                                    </div>
                                    <button type="button" :class="ui.secondaryButton" @click="addVariant">
                                        <Plus :size="17" />
                                        <span>Agregar</span>
                                    </button>
                                </div>
                                <div class="grid gap-3">
                                    <article v-for="(variant, index) in productDraft.variants" :key="variant.id" class="grid gap-3 rounded-md border border-slate-200 p-3 sm:grid-cols-[1fr_1fr_100px_40px]">
                                        <label :class="ui.label">
                                            Color
                                            <input v-model="variant.color" :name="`variant_${index}_color`" :class="ui.input" placeholder="Negro">
                                        </label>
                                        <label :class="ui.label">
                                            Talle
                                            <input v-model="variant.size" :name="`variant_${index}_size`" :class="ui.input" placeholder="M">
                                        </label>
                                        <label :class="ui.label">
                                            Stock
                                            <input v-model="variant.stock" :name="`variant_${index}_stock`" inputmode="numeric" :class="ui.input">
                                        </label>
                                        <button type="button" class="mt-6 inline-grid h-10 w-10 place-items-center rounded-md border border-slate-300 text-slate-600 hover:bg-slate-50" title="Eliminar variante" @click="removeVariant(index)">
                                            <Trash2 :size="17" />
                                        </button>
                                    </article>
                                </div>
                            </section>

                            <section class="grid gap-4">
                                <h3 class="text-sm font-semibold text-slate-900">Identificacion y estado</h3>
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <label :class="ui.label">
                                        SKU
                                        <input id="product-sku" v-model="productDraft.sku" name="product_sku" :placeholder="generatedSku" :class="[ui.input, 'uppercase']">
                                    </label>
                                    <label :class="ui.label">
                                        Barcode
                                        <input id="product-barcode" v-model="productDraft.barcode" name="product_barcode" :placeholder="generatedBarcode" :class="ui.input">
                                    </label>
                                </div>
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <label :class="ui.label">
                                        Categoria
                                        <select id="product-category" v-model="productDraft.category" name="product_category" :class="ui.input">
                                            <option v-for="category in categories" :key="category">{{ category }}</option>
                                        </select>
                                    </label>
                                    <label :class="ui.label">
                                        Estado
                                        <select id="product-status" v-model="productDraft.status" name="product_status" :class="ui.input">
                                            <option v-for="status in productStatuses.filter((status) => status !== 'Todos')" :key="status">{{ status }}</option>
                                        </select>
                                    </label>
                                </div>
                            </section>

                            <div class="sticky bottom-0 -mx-4 grid grid-cols-1 gap-2 border-t border-slate-200 bg-white/95 px-4 py-3 backdrop-blur sm:-mx-5 sm:grid-cols-2 sm:px-5">
                                <button type="button" :class="[ui.secondaryButton, 'hidden sm:inline-flex']" @click="previewProduct(normalizedDraft)">
                                    <Eye :size="18" />
                                    <span>Vista previa</span>
                                </button>
                                <button type="button" :class="ui.primaryButton" :disabled="productSaving" @click="saveProduct">
                                    <Save :size="18" />
                                    <span>{{ productSaving ? 'Guardando' : 'Guardar' }}</span>
                                </button>
                            </div>
                        </form>
                    </article>

                    <aside :class="[ui.card, 'p-4']">
                        <h2 class="text-base font-semibold">Resumen</h2>
                        <dl class="mt-4 grid gap-3 text-sm">
                            <div class="rounded-md border border-slate-200 p-3">
                                <dt class="text-xs text-slate-500">SKU final</dt>
                                <dd class="mt-1 font-medium">{{ normalizedDraft.sku }}</dd>
                            </div>
                            <div class="rounded-md border border-slate-200 p-3">
                                <dt class="text-xs text-slate-500">Barcode final</dt>
                                <dd class="mt-1 font-medium">{{ normalizedDraft.barcode }}</dd>
                            </div>
                            <div class="rounded-md border border-slate-200 p-3">
                                <dt class="text-xs text-slate-500">Variantes</dt>
                                <dd class="mt-1 font-medium">{{ productDraft.variants.length }}</dd>
                            </div>
                            <div class="rounded-md border border-slate-200 p-3">
                                <dt class="text-xs text-slate-500">Fotos</dt>
                                <dd class="mt-1 font-medium">{{ uploadedImages.length }}</dd>
                            </div>
                        </dl>
                        <div v-if="uploadedImages.length" class="mt-4 grid gap-2">
                            <div class="overflow-hidden rounded-md border border-emerald-500">
                                <img class="aspect-square w-full object-cover" :src="uploadedImages[0].url" :alt="uploadedImages[0].id || 'Foto principal'">
                            </div>
                            <p class="text-xs font-medium text-emerald-700">La primera foto se guarda como principal en Aimeos.</p>
                        </div>
                    </aside>
                </section>

                <section v-else-if="activeSection === 'products'" class="space-y-4">
                    <article :class="ui.card">
                        <module-toolbar
                            title="Productos"
                            :count="filteredProducts.length"
                            search-placeholder="Buscar por nombre, SKU o estado"
                            :search="productFilters.search"
                            :status="productFilters.status"
                            :sort="productFilters.sort"
                            :status-options="productStatuses"
                            :sort-options="productSortOptions"
                            @search="productFilters.search = $event"
                            @status="productFilters.status = $event"
                            @sort="productFilters.sort = $event"
                        />

                        <div class="grid gap-3 p-3 md:hidden">
                            <p v-if="productLoading" class="rounded-md border border-slate-200 bg-white p-4 text-sm text-slate-500">Cargando productos...</p>
                            <article v-for="product in filteredProducts" :key="product.sku" :class="ui.mobileRow">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <h3 class="font-medium text-slate-950">{{ product.name }}</h3>
                                        <p class="mt-1 text-sm text-slate-500">{{ product.sku }} · {{ product.vendor }}</p>
                                    </div>
                                    <status-pill :value="product.status" />
                                </div>
                                <dl class="grid grid-cols-3 gap-3 text-sm">
                                    <div>
                                        <dt class="text-xs text-slate-500">Precio</dt>
                                        <dd class="font-medium">{{ product.price }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs text-slate-500">Stock</dt>
                                        <dd class="font-medium">{{ product.stock }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs text-slate-500">Costo</dt>
                                        <dd class="font-medium">{{ product.cost }}</dd>
                                    </div>
                                </dl>
                                <div class="flex gap-2">
                                    <button type="button" :class="ui.secondaryButton" @click="editProduct(product)">
                                        <Pencil :size="17" />
                                        <span>Editar</span>
                                    </button>
                                    <button type="button" class="inline-flex h-10 items-center justify-center gap-2 rounded-md border border-red-200 bg-white px-3 text-sm font-medium text-red-700 hover:bg-red-50" @click="deleteProduct(product)">
                                        <Trash2 :size="17" />
                                        <span>Eliminar</span>
                                    </button>
                                </div>
                            </article>
                        </div>

                        <div class="hidden overflow-x-auto md:block">
                            <table :class="ui.table">
                                <thead :class="ui.thead">
                                    <tr>
                                        <th :class="ui.th">
                                            <sort-button label="Producto" field="name" :active-sort="productFilters.sort" @sort="productFilters.sort = $event" />
                                        </th>
                                        <th :class="ui.th">
                                            <sort-button label="SKU" field="sku" :active-sort="productFilters.sort" @sort="productFilters.sort = $event" />
                                        </th>
                                        <th :class="ui.th">Categoria</th>
                                        <th :class="ui.th">
                                            <sort-button label="Precio" field="price" :active-sort="productFilters.sort" @sort="productFilters.sort = $event" />
                                        </th>
                                        <th :class="ui.th">Stock</th>
                                        <th :class="ui.th">Estado</th>
                                        <th :class="[ui.th, 'text-right']">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody :class="ui.tbody">
                                    <tr v-if="productLoading">
                                        <td :class="ui.td" colspan="7">Cargando productos...</td>
                                    </tr>
                                    <tr v-for="product in filteredProducts" :key="product.sku" class="hover:bg-slate-50">
                                        <td :class="ui.td">
                                            <div class="font-medium text-slate-950">{{ product.name }}</div>
                                            <div class="text-xs text-slate-500">{{ product.vendor }}</div>
                                        </td>
                                        <td :class="ui.td">{{ product.sku }}</td>
                                        <td :class="ui.td">{{ product.category }}</td>
                                        <td :class="ui.td">{{ product.price }}</td>
                                        <td :class="ui.td">{{ product.stock }}</td>
                                        <td :class="ui.td"><status-pill :value="product.status" /></td>
                                        <td :class="[ui.td, 'text-right']">
                                            <div class="inline-flex gap-2">
                                                <button type="button" :class="ui.iconButton" title="Vista previa" @click="previewProduct(product)">
                                                    <Eye :size="17" />
                                                </button>
                                                <button type="button" :class="ui.iconButton" title="Editar" @click="editProduct(product)">
                                                    <Pencil :size="17" />
                                                </button>
                                                <button type="button" class="inline-grid h-10 w-10 place-items-center rounded-md border border-red-200 bg-white text-red-700 hover:bg-red-50" title="Eliminar" @click="deleteProduct(product)">
                                                    <Trash2 :size="17" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </article>
                </section>

                <section v-else-if="activeSection === 'overview'" class="space-y-4">
                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <article v-for="metric in metrics" :key="metric.label" :class="[ui.card, 'p-4']">
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-slate-500">{{ metric.label }}</span>
                                <component :is="metric.icon" class="text-emerald-600" :size="19" />
                            </div>
                            <strong class="mt-3 block text-2xl font-semibold tracking-normal">{{ metric.value }}</strong>
                            <p class="mt-1 text-sm text-slate-500">{{ metric.detail }}</p>
                        </article>
                    </div>

                    <article :class="ui.card">
                        <div class="border-b border-slate-200 px-4 py-3 sm:px-5">
                            <h2 class="text-base font-semibold">Actividad comercial</h2>
                        </div>
                        <div class="grid gap-3 p-3 md:grid-cols-2 xl:grid-cols-4">
                            <article v-for="item in activity" :key="item.area" class="rounded-md border border-slate-200 p-4">
                                <div class="flex items-center justify-between gap-3">
                                    <h3 class="font-medium">{{ item.area }}</h3>
                                    <status-pill :value="item.status" />
                                </div>
                                <p class="mt-3 text-sm text-slate-600">{{ item.operation }}</p>
                                <p class="mt-2 text-xs font-medium uppercase tracking-wide text-slate-400">{{ item.channel }}</p>
                            </article>
                        </div>
                    </article>
                </section>

                <section v-else-if="activeSection === 'store'" class="grid gap-4 xl:grid-cols-2">
                    <settings-panel title="Tienda" :fields="storeFields" :saving="settingsSaving" @update="updateSetting" @save="saveSettings" />
                    <settings-panel title="Monedas" :fields="currencyFields" :saving="settingsSaving" @update="updateSetting" @save="saveSettings" />
                </section>

                <section v-else-if="resourceModule" class="space-y-4">
                    <article :class="ui.card">
                        <module-toolbar
                            :title="resourceModule.title"
                            :count="filteredResourceRows.length"
                            :search-placeholder="resourceModule.searchPlaceholder"
                            :search="resourceFilters.search"
                            :status="resourceFilters.status"
                            :sort="resourceFilters.sort"
                            :status-options="resourceModule.statusOptions"
                            :sort-options="resourceModule.sortOptions"
                            @search="resourceFilters.search = $event"
                            @status="resourceFilters.status = $event"
                            @sort="resourceFilters.sort = $event"
                        />

                        <div class="grid gap-3 p-3 md:hidden">
                            <p v-if="resourceLoading" class="rounded-md border border-slate-200 bg-white p-4 text-sm text-slate-500">Cargando {{ resourceModule.title.toLowerCase() }}...</p>
                            <p v-else-if="filteredResourceRows.length === 0" class="rounded-md border border-slate-200 bg-white p-4 text-sm text-slate-500">{{ emptyResourceMessage }}</p>
                            <article v-for="row in filteredResourceRows" :key="row.id" :class="ui.mobileRow">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <h3 class="font-medium text-slate-950">{{ row.name }}</h3>
                                        <p class="mt-1 text-sm text-slate-500">{{ row.meta }}</p>
                                    </div>
                                    <status-pill :value="row.status" />
                                </div>
                                <dl class="grid grid-cols-2 gap-3 text-sm">
                                    <div v-for="column in resourceModule.mobileFields" :key="column.key">
                                        <dt class="text-xs text-slate-500">{{ column.label }}</dt>
                                        <dd class="font-medium">{{ row[column.key] }}</dd>
                                    </div>
                                </dl>
                                <button v-if="activeSection !== 'orders'" type="button" :class="ui.secondaryButton" @click="editResource(row)">
                                    <Pencil :size="17" />
                                    <span>Editar</span>
                                </button>
                            </article>
                        </div>

                        <div class="hidden overflow-x-auto md:block">
                            <table :class="ui.table">
                                <thead :class="ui.thead">
                                    <tr>
                                        <th v-for="column in resourceModule.columns" :key="column.key" :class="ui.th">
                                            <sort-button
                                                v-if="column.sortable"
                                                :label="column.label"
                                                :field="column.key"
                                                :active-sort="resourceFilters.sort"
                                                @sort="resourceFilters.sort = $event"
                                            />
                                            <span v-else>{{ column.label }}</span>
                                        </th>
                                        <th :class="[ui.th, 'text-right']">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody :class="ui.tbody">
                                    <tr v-if="resourceLoading">
                                        <td :class="ui.td" :colspan="resourceModule.columns.length + 1">Cargando {{ resourceModule.title.toLowerCase() }}...</td>
                                    </tr>
                                    <tr v-else-if="filteredResourceRows.length === 0">
                                        <td :class="ui.td" :colspan="resourceModule.columns.length + 1">{{ emptyResourceMessage }}</td>
                                    </tr>
                                    <tr v-for="row in filteredResourceRows" :key="row.id" class="hover:bg-slate-50">
                                        <td v-for="column in resourceModule.columns" :key="column.key" :class="ui.td">
                                            <status-pill v-if="column.key === 'status'" :value="row.status" />
                                            <span v-else>{{ row[column.key] }}</span>
                                        </td>
                                        <td :class="[ui.td, 'text-right']">
                                            <button v-if="activeSection !== 'orders'" type="button" :class="ui.iconButton" title="Editar" @click="editResource(row)">
                                                <Pencil :size="17" />
                                            </button>
                                            <span v-else class="text-xs text-slate-400">Solo lectura</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </article>
                </section>

                <section v-else class="grid gap-4 xl:grid-cols-[1fr_360px]">
                    <article :class="ui.card">
                        <module-toolbar
                            title="Usuarios"
                            :count="team.length"
                            search-placeholder="Buscar usuarios"
                            search=""
                            status="Todos"
                            sort="name:asc"
                            :status-options="['Todos', 'Activo']"
                            :sort-options="[{ label: 'Nombre', value: 'name:asc' }]"
                        />
                        <div class="divide-y divide-slate-100">
                            <p v-if="userLoading" class="px-4 py-4 text-sm text-slate-500 sm:px-5">Cargando usuarios...</p>
                            <div v-for="member in team" :key="member.email" class="flex items-center justify-between gap-4 px-4 py-4 sm:px-5">
                                <div>
                                    <p class="font-medium text-slate-900">{{ member.name }}</p>
                                    <p class="text-sm text-slate-500">{{ member.email }} · {{ member.status }}</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="rounded-md bg-indigo-50 px-2 py-1 text-xs font-medium text-indigo-700">{{ member.role }}</span>
                                    <button type="button" :class="ui.iconButton" title="Editar" @click="editUser(member)">
                                        <Pencil :size="17" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </article>

                    <article :class="[ui.card, 'p-4']">
                        <h2 class="text-base font-semibold">Roles</h2>
                        <div class="mt-4 grid gap-3">
                            <label v-for="role in roles" :key="role.name" class="flex items-start gap-3 rounded-md border border-slate-200 p-3">
                                <input class="mt-1 h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-600" type="checkbox" :name="`role_${role.name.toLowerCase()}`" :checked="role.enabled">
                                <span>
                                    <strong class="block text-sm font-medium text-slate-900">{{ role.name }}</strong>
                                    <small class="text-sm text-slate-500">{{ role.scope }}</small>
                                </span>
                            </label>
                        </div>
                    </article>
                </section>

                <p v-if="message" class="rounded-md border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600 shadow-sm">{{ message }}</p>
            </div>
        </section>

        <div v-if="previewItem" class="fixed inset-0 z-40 grid place-items-end bg-slate-950/40 p-0 sm:place-items-center sm:p-4" role="dialog" aria-modal="true">
            <article class="max-h-[92vh] w-full overflow-y-auto rounded-t-lg bg-white shadow-xl sm:max-w-xl sm:rounded-lg">
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                    <h2 class="text-base font-semibold">Vista previa</h2>
                    <button type="button" :class="ui.iconButton" title="Cerrar" @click="previewItem = null">
                        <X :size="18" />
                    </button>
                </div>
                <div class="p-5">
                    <div class="aspect-[4/3] rounded-lg border border-slate-200 bg-slate-100 p-5">
                        <div class="flex h-full flex-col justify-between rounded-md bg-white p-5 shadow-sm">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">{{ previewItem.category || 'Catalogo' }}</p>
                                <h3 class="mt-2 text-2xl font-semibold tracking-normal">{{ previewItem.name }}</h3>
                                <p class="mt-3 text-sm text-slate-600">{{ previewItem.description || 'Producto preparado para publicar en el frontend custom.' }}</p>
                            </div>
                            <div class="flex items-end justify-between gap-4">
                                <div>
                                    <p class="text-xs text-slate-500">SKU</p>
                                    <p class="font-medium">{{ previewItem.sku }}</p>
                                </div>
                                <p class="text-2xl font-semibold">{{ previewItem.price }}</p>
                            </div>
                        </div>
                    </div>
                    <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                        <div class="rounded-md border border-slate-200 p-3">
                            <dt class="text-xs text-slate-500">Estado</dt>
                            <dd class="mt-1 font-medium">{{ previewItem.status }}</dd>
                        </div>
                        <div class="rounded-md border border-slate-200 p-3">
                            <dt class="text-xs text-slate-500">Proveedor</dt>
                            <dd class="mt-1 font-medium">{{ previewItem.vendor || 'Sin proveedor' }}</dd>
                        </div>
                    </dl>
                </div>
            </article>
        </div>
    </main>
</template>

<script setup>
import {
    ArrowLeft,
    Banknote,
    Boxes,
    CreditCard,
    Eye,
    FolderTree,
    LayoutDashboard,
    LogIn,
    Package,
    Pencil,
    Plus,
    ReceiptText,
    RefreshCw,
    Save,
    Search,
    ShieldCheck,
    Star,
    Store,
    Trash2,
    Truck,
    Users,
    WalletCards,
    X,
} from '@lucide/vue';
import { computed, defineComponent, h, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import vueFilePond from 'vue-filepond';
import FilePondPluginFileValidateType from 'filepond-plugin-file-validate-type';
import FilePondPluginImagePreview from 'filepond-plugin-image-preview';
import FilePondPluginImageResize from 'filepond-plugin-image-resize';
import FilePondPluginImageTransform from 'filepond-plugin-image-transform';
import 'filepond/dist/filepond.min.css';
import 'filepond-plugin-image-preview/dist/filepond-plugin-image-preview.css';

const FilePond = vueFilePond(
    FilePondPluginFileValidateType,
    FilePondPluginImagePreview,
    FilePondPluginImageResize,
    FilePondPluginImageTransform,
);

const ui = {
    card: 'overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm',
    iconButton: 'inline-grid h-10 w-10 place-items-center rounded-md border border-slate-300 bg-white text-slate-700 hover:bg-slate-50',
    input: 'h-10 w-full rounded-md border border-slate-300 px-3 text-sm font-normal outline-none focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100',
    label: 'grid gap-1 text-sm font-medium text-slate-700',
    mobileRow: 'grid gap-3 rounded-lg border border-slate-200 bg-white p-4 shadow-sm',
    mobileTab: 'inline-flex h-9 shrink-0 items-center gap-2 rounded-md border px-3 text-sm',
    mobileTabActive: 'border-emerald-600 bg-emerald-50 text-emerald-700',
    mobileTabIdle: 'border-slate-300 bg-white text-slate-700',
    navButton: 'flex h-10 w-full items-center gap-3 rounded-md px-3 text-left text-sm font-medium transition',
    navButtonActive: 'bg-emerald-50 text-emerald-700',
    navButtonIdle: 'text-slate-600 hover:bg-slate-100 hover:text-slate-950',
    primaryButton: 'inline-flex h-10 items-center justify-center gap-2 rounded-md bg-emerald-600 px-4 text-sm font-medium text-white hover:bg-emerald-700 disabled:cursor-wait disabled:opacity-70',
    secondaryButton: 'inline-flex h-10 items-center justify-center gap-2 rounded-md border border-slate-300 bg-white px-3 text-sm font-medium text-slate-700 hover:bg-slate-50',
    table: 'w-full min-w-[760px] text-left text-sm',
    tbody: 'divide-y divide-slate-100',
    td: 'px-4 py-4 align-middle text-slate-600 sm:px-5',
    th: 'px-4 py-3 text-left font-medium sm:px-5',
    thead: 'bg-slate-50 text-xs uppercase text-slate-500',
};

const StatusPill = defineComponent({
    props: {
        value: { type: String, required: true },
    },
    setup(props) {
        const classes = computed(() => {
            if (['Activo', 'Publicado', 'Pagado', 'Habilitado'].includes(props.value)) {
                return 'bg-emerald-50 text-emerald-700';
            }

            if (['Borrador', 'Pendiente', 'Revision'].includes(props.value)) {
                return 'bg-amber-50 text-amber-700';
            }

            return 'bg-slate-100 text-slate-700';
        });

        return () => h('span', { class: ['rounded-md px-2 py-1 text-xs font-medium', classes.value] }, props.value);
    },
});

const SortButton = defineComponent({
    props: {
        label: { type: String, required: true },
        field: { type: String, required: true },
        activeSort: { type: String, required: true },
    },
    emits: ['sort'],
    setup(props, { emit }) {
        const direction = computed(() => props.activeSort === `${props.field}:asc` ? 'desc' : 'asc');
        const active = computed(() => props.activeSort.startsWith(`${props.field}:`));

        return () => h('button', {
            class: 'inline-flex items-center gap-1 text-xs font-medium uppercase text-slate-500 hover:text-slate-950',
            type: 'button',
            onClick: () => emit('sort', `${props.field}:${direction.value}`),
        }, [
            props.label,
            h('span', { class: active.value ? 'text-emerald-700' : 'text-slate-300' }, active.value && props.activeSort.endsWith(':desc') ? '↓' : '↑'),
        ]);
    },
});

const ModuleToolbar = defineComponent({
    props: {
        title: { type: String, required: true },
        count: { type: Number, required: true },
        searchPlaceholder: { type: String, required: true },
        search: { type: String, required: true },
        status: { type: String, required: true },
        sort: { type: String, required: true },
        statusOptions: { type: Array, required: true },
        sortOptions: { type: Array, required: true },
    },
    emits: ['search', 'status', 'sort'],
    setup(props, { emit }) {
        return () => h('div', { class: 'space-y-3 border-b border-slate-200 px-4 py-4 sm:px-5' }, [
            h('div', { class: 'flex flex-wrap items-center justify-between gap-3' }, [
                h('div', [
                    h('h2', { class: 'text-base font-semibold' }, props.title),
                    h('p', { class: 'mt-1 text-sm text-slate-500' }, `${props.count} registros`),
                ]),
            ]),
            h('div', { class: 'grid gap-2 sm:grid-cols-[minmax(0,1fr)_160px_180px]' }, [
                h('div', { class: 'relative' }, [
                    h(Search, { class: 'absolute left-3 top-2.5 text-slate-400', size: 17 }),
                    h('input', {
                        class: ui.input + ' pl-9',
                        name: `${props.title.toLowerCase()}_search`,
                        placeholder: props.searchPlaceholder,
                        value: props.search,
                        onInput: (event) => emit('search', event.target.value),
                    }),
                ]),
                h('select', {
                    class: ui.input,
                    name: `${props.title.toLowerCase()}_status`,
                    value: props.status,
                    onChange: (event) => emit('status', event.target.value),
                }, props.statusOptions.map((option) => h('option', { key: option }, option))),
                h('select', {
                    class: ui.input,
                    name: `${props.title.toLowerCase()}_sort`,
                    value: props.sort,
                    onChange: (event) => emit('sort', event.target.value),
                }, props.sortOptions.map((option) => h('option', { key: option.value, value: option.value }, option.label))),
            ]),
        ]);
    },
});

const SettingsPanel = defineComponent({
    props: {
        title: { type: String, required: true },
        fields: { type: Array, required: true },
        saving: { type: Boolean, default: false },
    },
    emits: ['update', 'save'],
    setup(props, { emit }) {
        return () => h('article', { class: ui.card + ' p-4 sm:p-5' }, [
            h('h2', { class: 'text-base font-semibold' }, props.title),
            h('div', { class: 'mt-4 grid gap-4' }, props.fields.map((field) => h('label', { class: ui.label, key: field.label }, [
                field.label,
                h('input', {
                    class: ui.input,
                    name: field.key,
                    value: field.value,
                    onInput: (event) => emit('update', field.key, event.target.value),
                }),
            ]))),
            h('button', {
                class: ui.primaryButton + ' mt-5',
                disabled: props.saving,
                type: 'button',
                onClick: () => emit('save'),
            }, [
                h(Save, { size: 18 }),
                h('span', props.saving ? 'Guardando' : 'Guardar'),
            ]),
        ]);
    },
});

const user = ref(window.__MANAGE_USER__ || null);
const message = ref('');
const previewItem = ref(null);
const activeSection = ref('products');
const editingProductSku = ref(null);
const productFiles = ref([]);
const uploadedImages = ref([]);
const productNotice = ref('');
const productErrors = ref({});
const productLoading = ref(false);
const productSaving = ref(false);
const savedProductSnapshot = ref('');
const resourceLoading = ref(false);
const settingsSaving = ref(false);
const userLoading = ref(false);
const resourceRows = reactive({
    categories: [],
    orders: [],
    payments: [],
});
const shopSettings = reactive({
    commercial_name: 'Default',
    site_code: 'default',
    contact_email: 'admin@test.com',
    country: 'US',
    primary_currency: 'USD',
    secondary_currency: 'EUR',
    primary_language: 'es',
    timezone: 'America/Argentina/Buenos_Aires',
});
const productFilters = reactive({
    search: '',
    status: 'Todos',
    sort: 'name:asc',
});
const resourceFilters = reactive({
    search: '',
    status: 'Todos',
    sort: 'name:asc',
});
const productDraft = reactive(newProductDraft());

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
const pondServer = {
    process(fieldName, file, metadata, load, error, progress, abort) {
        const formData = new FormData();
        const filename = file.name || `product-${Date.now()}.jpg`;
        formData.append(fieldName, file, filename);

        const request = new XMLHttpRequest();
        request.open('POST', '/admin/uploads/product-images');
        request.setRequestHeader('Accept', 'application/json');

        if (csrfToken) {
            request.setRequestHeader('X-CSRF-TOKEN', csrfToken);
        }

        request.upload.onprogress = (event) => {
            progress(event.lengthComputable, event.loaded, event.total);
        };

        request.onload = () => {
            if (request.status < 200 || request.status >= 300) {
                productErrors.value = { upload: uploadErrorMessage(request) };
                error(productErrors.value.upload);
                return;
            }

            try {
                const payload = JSON.parse(request.responseText);
                rememberUploadedImage(payload);
                productErrors.value = {};
                productNotice.value = 'Foto subida.';
                load(payload.id);
            } catch {
                productErrors.value = { upload: 'La imagen subio, pero la respuesta no se pudo leer.' };
                error(productErrors.value.upload);
            }
        };

        request.onerror = () => {
            productErrors.value = { upload: 'No se pudo subir la imagen.' };
            error(productErrors.value.upload);
        };

        request.send(formData);

        return {
            abort: () => {
                request.abort();
                abort();
            },
        };
    },
};

const navItems = [
    { key: 'products', label: 'Productos', title: 'Productos', action: 'Crear producto', icon: Package },
    { key: 'categories', label: 'Categorias', title: 'Categorias', action: 'Crear categoria', icon: FolderTree },
    { key: 'suppliers', label: 'Proveedores', title: 'Proveedores', action: 'Crear proveedor', icon: Truck },
    { key: 'costs', label: 'Costos', title: 'Costos', action: 'Agregar costo', icon: WalletCards },
    { key: 'payments', label: 'Metodos de pago', title: 'Metodos de pago', action: 'Agregar metodo', icon: CreditCard },
    { key: 'orders', label: 'Ordenes', title: 'Ordenes', action: 'Exportar', icon: ReceiptText },
    { key: 'store', label: 'Tienda', title: 'Configuracion', action: 'Guardar', icon: Store },
    { key: 'users', label: 'Usuarios y roles', title: 'Usuarios y roles', action: 'Invitar usuario', icon: Users },
    { key: 'overview', label: 'Resumen', title: 'Panel operativo', action: 'Crear producto', icon: LayoutDashboard },
];

const productStatuses = ['Todos', 'Publicado', 'Borrador', 'Pausado'];
const productSortOptions = [
    { label: 'Nombre A-Z', value: 'name:asc' },
    { label: 'Nombre Z-A', value: 'name:desc' },
    { label: 'SKU A-Z', value: 'sku:asc' },
    { label: 'Precio menor', value: 'price:asc' },
    { label: 'Precio mayor', value: 'price:desc' },
];

const metrics = [
    { label: 'Productos', value: '3', detail: 'Catalogo inicial', icon: Boxes },
    { label: 'Ordenes', value: '2', detail: 'Pendientes de conectar', icon: ReceiptText },
    { label: 'Moneda base', value: 'USD', detail: 'Locale currency', icon: Banknote },
    { label: 'Roles activos', value: '2', detail: 'Admin y editor', icon: ShieldCheck },
];

const activity = [
    { area: 'Catalogo', status: 'Activo', operation: 'Productos, categorias, proveedores y costos', channel: 'Aimeos' },
    { area: 'Tienda', status: 'Activo', operation: 'Sitio, idioma y moneda', channel: 'Aimeos' },
    { area: 'Pagos', status: 'Pendiente', operation: 'Metodos de pago propios', channel: 'Aimeos service' },
    { area: 'Ordenes', status: 'Activo', operation: 'Lectura y seguimiento', channel: 'Aimeos order' },
];

const products = ref([]);

const resourceModules = {
    categories: {
        title: 'Categorias',
        searchPlaceholder: 'Buscar categorias',
        statusOptions: ['Todos', 'Activo', 'Borrador'],
        sortOptions: [{ label: 'Nombre A-Z', value: 'name:asc' }, { label: 'Nombre Z-A', value: 'name:desc' }],
        columns: [
            { key: 'name', label: 'Categoria', sortable: true },
            { key: 'meta', label: 'Slug', sortable: true },
            { key: 'products', label: 'Productos', sortable: true },
            { key: 'status', label: 'Estado', sortable: true },
        ],
        mobileFields: [{ key: 'products', label: 'Productos' }, { key: 'status', label: 'Estado' }],
        rows: resourceRows.categories,
    },
    suppliers: {
        title: 'Proveedores',
        searchPlaceholder: 'Buscar proveedores',
        statusOptions: ['Todos', 'Activo', 'Revision'],
        sortOptions: [{ label: 'Nombre A-Z', value: 'name:asc' }, { label: 'Contacto A-Z', value: 'meta:asc' }],
        columns: [
            { key: 'name', label: 'Proveedor', sortable: true },
            { key: 'meta', label: 'Contacto', sortable: true },
            { key: 'leadTime', label: 'Lead time', sortable: true },
            { key: 'status', label: 'Estado', sortable: true },
        ],
        mobileFields: [{ key: 'leadTime', label: 'Lead time' }, { key: 'status', label: 'Estado' }],
        rows: [
            { id: 'sup-1', name: 'Proveedor demo', meta: 'compras@test.com', leadTime: '3 dias', status: 'Activo' },
            { id: 'sup-2', name: 'Interno', meta: 'ops@test.com', leadTime: 'Inmediato', status: 'Activo' },
        ],
    },
    costs: {
        title: 'Costos',
        searchPlaceholder: 'Buscar costos',
        statusOptions: ['Todos', 'Activo', 'Revision'],
        sortOptions: [{ label: 'Nombre A-Z', value: 'name:asc' }, { label: 'Costo menor', value: 'amount:asc' }, { label: 'Costo mayor', value: 'amount:desc' }],
        columns: [
            { key: 'name', label: 'Concepto', sortable: true },
            { key: 'meta', label: 'Producto', sortable: true },
            { key: 'amount', label: 'Costo', sortable: true },
            { key: 'status', label: 'Estado', sortable: true },
        ],
        mobileFields: [{ key: 'meta', label: 'Producto' }, { key: 'amount', label: 'Costo' }],
        rows: [
            { id: 'cost-1', name: 'Costo proveedor', meta: 'DEMO-001', amount: '9.80', status: 'Activo' },
            { id: 'cost-2', name: 'Packaging', meta: 'DEMO-002', amount: '2.40', status: 'Revision' },
        ],
    },
    payments: {
        title: 'Metodos de pago',
        searchPlaceholder: 'Buscar metodos',
        statusOptions: ['Todos', 'Activo', 'Borrador'],
        sortOptions: [{ label: 'Nombre A-Z', value: 'name:asc' }, { label: 'Proveedor A-Z', value: 'meta:asc' }],
        columns: [
            { key: 'name', label: 'Metodo', sortable: true },
            { key: 'meta', label: 'Proveedor', sortable: true },
            { key: 'fee', label: 'Comision', sortable: true },
            { key: 'status', label: 'Estado', sortable: true },
        ],
        mobileFields: [{ key: 'meta', label: 'Proveedor' }, { key: 'fee', label: 'Comision' }],
        rows: resourceRows.payments,
    },
    orders: {
        title: 'Ordenes',
        searchPlaceholder: 'Buscar ordenes',
        statusOptions: ['Todos', 'Sin finalizar', 'Pendiente', 'Autorizado', 'Pagado', 'Cancelado', 'Rechazado', 'Reembolsado', 'Transferido'],
        sortOptions: [{ label: 'Orden A-Z', value: 'name:asc' }, { label: 'Total menor', value: 'total:asc' }, { label: 'Total mayor', value: 'total:desc' }],
        columns: [
            { key: 'name', label: 'Orden', sortable: true },
            { key: 'meta', label: 'Cliente', sortable: true },
            { key: 'total', label: 'Total', sortable: true },
            { key: 'status', label: 'Pago', sortable: true },
        ],
        mobileFields: [{ key: 'total', label: 'Total' }, { key: 'delivery', label: 'Entrega' }],
        rows: resourceRows.orders,
    },
};

const storeFields = computed(() => [
    { key: 'commercial_name', label: 'Nombre comercial', value: shopSettings.commercial_name },
    { key: 'site_code', label: 'Codigo de sitio', value: shopSettings.site_code },
    { key: 'contact_email', label: 'Email de contacto', value: shopSettings.contact_email },
    { key: 'country', label: 'Pais', value: shopSettings.country },
]);

const currencyFields = computed(() => [
    { key: 'primary_currency', label: 'Moneda principal', value: shopSettings.primary_currency },
    { key: 'secondary_currency', label: 'Moneda secundaria', value: shopSettings.secondary_currency },
    { key: 'primary_language', label: 'Idioma principal', value: shopSettings.primary_language },
    { key: 'timezone', label: 'Zona horaria', value: shopSettings.timezone },
]);

const team = ref([]);

const roles = [
    { name: 'Superadmin', scope: 'Crear tienda, usuarios y roles', enabled: true },
    { name: 'Admin', scope: 'Gestionar tienda, monedas, productos y ordenes', enabled: true },
    { name: 'Editor', scope: 'Catalogo y CMS', enabled: false },
];

const categories = computed(() => resourceRows.categories.length ? resourceRows.categories.map((category) => category.name) : ['General']);
const activeItem = computed(() => {
    if (activeSection.value === 'product-form') {
        return {
            key: 'product-form',
            label: 'Producto',
            title: productFormTitle.value,
            action: 'Guardar',
            icon: Package,
        };
    }

    return navItems.find((item) => item.key === activeSection.value) || navItems[0];
});
const resourceModule = computed(() => resourceModules[activeSection.value] || null);
const userLabel = computed(() => user.value ? `${user.value.name} · ${user.value.email}` : 'Sin sesion activa');
const sessionLabel = computed(() => user.value ? 'Sesion web y API' : 'Login requerido');
const productFormTitle = computed(() => editingProductSku.value ? 'Editar producto' : 'Crear producto');
const filteredProducts = computed(() => filterAndSortRows(products.value, productFilters, ['name', 'sku', 'status', 'category', 'vendor']));
const generatedSku = computed(() => generateSku(productDraft.name));
const generatedBarcode = computed(() => generateBarcode(productDraft.name, productDraft.price));
const normalizedDraft = computed(() => ({
    ...productDraft,
    sku: productDraft.sku.trim() || generatedSku.value,
    barcode: String(productDraft.barcode || '').trim() || generatedBarcode.value,
    variants: productDraft.variants || [],
    images: uploadedImages.value,
}));
const hasUnsavedProductChanges = computed(() => (
    activeSection.value === 'product-form'
    && savedProductSnapshot.value !== ''
    && productSnapshot() !== savedProductSnapshot.value
));
const filteredResourceRows = computed(() => {
    if (!resourceModule.value) {
        return [];
    }

    return filterAndSortRows(resourceModule.value.rows, resourceFilters, ['name', 'meta', 'status']);
});
const emptyResourceMessage = computed(() => {
    if (activeSection.value === 'orders') {
        return 'No hay ordenes guardadas en Aimeos.';
    }

    if (activeSection.value === 'categories') {
        return 'No hay categorias guardadas en Aimeos.';
    }

    return 'No hay registros.';
});

watch(activeSection, () => {
    resourceFilters.search = '';
    resourceFilters.status = 'Todos';
    resourceFilters.sort = 'name:asc';
});

function newProductDraft() {
    return {
        name: '',
        sku: '',
        barcode: '',
        category: 'General',
        vendor: 'Proveedor demo',
        price: '0.00',
        cost: '0.00',
        stock: 0,
        status: 'Borrador',
        description: '',
        variants: [
            { id: createLocalId(), color: '', size: '', stock: 0 },
        ],
    };
}

function openSection(key) {
    if (!confirmLeaveDirty()) {
        return;
    }

    activeSection.value = key;

    if (key !== 'product-form') {
        pushAdminPath(key === 'products' ? '/admin' : `/admin/${key}`);
    }

    if (['categories', 'orders', 'payments'].includes(key)) {
        loadResourceRows(key);
    }

    if (key === 'store') {
        loadSettings();
    }

    if (key === 'users') {
        loadUsers();
    }
}

function isNavActive(key) {
    return activeSection.value === key || (key === 'products' && activeSection.value === 'product-form');
}

function primaryAction() {
    if (activeSection.value === 'product-form') {
        saveProduct();
        return;
    }

    createPrimary();
}

function createPrimary() {
    if (activeSection.value === 'store') {
        saveSettings();
        return;
    }

    if (activeSection.value === 'payments') {
        createPayment();
        return;
    }

    if (activeSection.value === 'users') {
        createUser();
        return;
    }

    if (activeSection.value !== 'products') {
        return;
    }

    Object.assign(productDraft, newProductDraft());
    editingProductSku.value = null;
    productFiles.value = [];
    uploadedImages.value = [];
    productNotice.value = '';
    productErrors.value = {};
    activeSection.value = 'product-form';
    savedProductSnapshot.value = productSnapshot();
    pushAdminPath('/admin/product/new');
}

async function editProduct(product) {
    if (!confirmLeaveDirty()) {
        return;
    }

    const loadedProduct = await loadProduct(product.sku);

    Object.assign(productDraft, {
        ...newProductDraft(),
        ...loadedProduct,
        barcode: loadedProduct.barcode || '',
        variants: loadedProduct.variants?.length ? loadedProduct.variants : [
            { id: createLocalId(), color: '', size: '', stock: loadedProduct.stock || 0 },
        ],
    });
    editingProductSku.value = loadedProduct.sku;
    productFiles.value = [];
    uploadedImages.value = normalizedImages(loadedProduct.images || []);
    productNotice.value = '';
    productErrors.value = {};
    activeSection.value = 'product-form';
    savedProductSnapshot.value = productSnapshot();
    pushAdminPath(`/admin/product/${encodeURIComponent(loadedProduct.sku)}`);
}

async function editResource(row) {
    if (activeSection.value === 'payments') {
        await savePayment(row);
        return;
    }

    if (activeSection.value !== 'categories') {
        message.value = 'Ordenes se muestran desde Aimeos en modo lectura.';
        return;
    }

    const name = window.prompt('Nombre de categoria', row.name);

    if (name === null) {
        return;
    }

    const status = window.confirm('Aceptar para dejarla Activo. Cancelar para Borrador.') ? 'Activo' : 'Borrador';

    try {
        const payload = await apiRequest(`/admin/api/categories/${encodeURIComponent(row.id)}`, {
            method: 'PUT',
            body: JSON.stringify({
                name: name.trim(),
                slug: row.meta,
                status,
            }),
        });
        const index = resourceRows.categories.findIndex((category) => category.id === row.id);

        if (index >= 0) {
            resourceRows.categories.splice(index, 1, payload.data);
        }

        products.value = products.value.map((product) => (
            product.category === row.name ? { ...product, category: payload.data.name } : product
        ));
        message.value = payload.message || 'Categoria guardada.';
    } catch (error) {
        message.value = normalizeApiErrors(error).api || 'No se pudo guardar la categoria.';
    }
}

async function createPayment() {
    await savePayment();
}

async function savePayment(row = null) {
    const name = window.prompt('Nombre del metodo de pago', row?.name || '');

    if (name === null) {
        return;
    }

    const provider = window.prompt('Proveedor', row?.meta || 'Manual');

    if (provider === null) {
        return;
    }

    const fee = window.prompt('Comision', row?.fee || '0%');

    if (fee === null) {
        return;
    }

    const status = window.confirm('Aceptar para dejarlo Activo. Cancelar para Borrador.') ? 'Activo' : 'Borrador';

    try {
        const endpoint = row ? `/admin/api/payments/${encodeURIComponent(row.id)}` : '/admin/api/payments';
        const payload = await apiRequest(endpoint, {
            method: row ? 'PUT' : 'POST',
            body: JSON.stringify({
                name: name.trim(),
                provider: provider.trim(),
                fee: fee.trim(),
                status,
            }),
        });
        const index = resourceRows.payments.findIndex((payment) => payment.id === payload.data.id);

        if (index >= 0) {
            resourceRows.payments.splice(index, 1, payload.data);
        } else {
            resourceRows.payments.unshift(payload.data);
        }

        message.value = payload.message || 'Metodo de pago guardado.';
    } catch (error) {
        message.value = normalizeApiErrors(error).api || 'No se pudo guardar el metodo de pago.';
    }
}

async function createUser() {
    await saveUser();
}

async function editUser(member) {
    await saveUser(member);
}

async function saveUser(member = null) {
    const name = window.prompt('Nombre', member?.name || '');

    if (name === null) {
        return;
    }

    const email = window.prompt('Email', member?.email || '');

    if (email === null) {
        return;
    }

    const role = window.prompt('Rol: Superadmin, Admin o Editor', member?.role || 'Admin');

    if (role === null) {
        return;
    }

    const status = window.confirm('Aceptar para dejarlo Activo. Cancelar para Inactivo.') ? 1 : 0;

    try {
        const endpoint = member ? `/admin/api/users/${encodeURIComponent(member.id)}` : '/admin/api/users';
        const payload = await apiRequest(endpoint, {
            method: member ? 'PUT' : 'POST',
            body: JSON.stringify({
                name: name.trim(),
                email: email.trim(),
                role: role.trim(),
                status,
                siteid: shopSettings.site_code || 'default',
            }),
        });
        const index = team.value.findIndex((user) => user.id === payload.data.id);

        if (index >= 0) {
            team.value.splice(index, 1, payload.data);
        } else {
            team.value.unshift(payload.data);
        }

        message.value = payload.message || 'Usuario guardado.';
    } catch (error) {
        message.value = normalizeApiErrors(error).api || 'No se pudo guardar el usuario.';
    }
}

function previewProduct(product) {
    previewItem.value = { ...product };
}

async function saveProduct() {
    productErrors.value = validateProduct(normalizedDraft.value);
    productNotice.value = '';

    if (Object.keys(productErrors.value).length > 0) {
        return;
    }

    productSaving.value = true;

    const product = {
        ...normalizedDraft.value,
        name: normalizedDraft.value.name.trim(),
        price: formatMoney(normalizedDraft.value.price),
        cost: formatMoney(normalizedDraft.value.cost || '0'),
        stock: totalVariantStock(normalizedDraft.value.variants),
        variants: normalizedDraft.value.variants
            .filter((variant) => variant.color || variant.size || Number(variant.stock) > 0)
            .map((variant) => ({
                ...variant,
                stock: Number(variant.stock) || 0,
            })),
        imageCount: uploadedImages.value.length,
        images: normalizedImages(),
    };

    try {
        const endpoint = editingProductSku.value
            ? `/admin/api/products/${encodeURIComponent(editingProductSku.value)}`
            : '/admin/api/products';
        const payload = await apiRequest(endpoint, {
            method: editingProductSku.value ? 'PUT' : 'POST',
            body: JSON.stringify(product),
        });
        const saved = payload.data;
        const index = products.value.findIndex((item) => item.sku === editingProductSku.value || item.sku === saved.sku);

        if (index >= 0) {
            products.value.splice(index, 1, saved);
        } else {
            products.value.unshift(saved);
        }

        Object.assign(productDraft, {
            ...newProductDraft(),
            ...saved,
            variants: saved.variants?.length ? saved.variants : [{ id: createLocalId(), color: '', size: '', stock: 0 }],
        });
        editingProductSku.value = saved.sku;
        uploadedImages.value = normalizedImages(saved.images || []);
        productNotice.value = payload.message || 'Producto guardado.';
        productErrors.value = {};
        savedProductSnapshot.value = productSnapshot();
        pushAdminPath(`/admin/product/${encodeURIComponent(saved.sku)}`);
    } catch (error) {
        productErrors.value = normalizeApiErrors(error);
    } finally {
        productSaving.value = false;
    }
}

async function deleteProduct(product) {
    if (!window.confirm(`Eliminar ${product.name}? Esta accion no se puede deshacer.`)) {
        return;
    }

    productSaving.value = true;
    productNotice.value = '';

    try {
        const payload = await apiRequest(`/admin/api/products/${encodeURIComponent(product.sku)}`, {
            method: 'DELETE',
        });
        products.value = products.value.filter((item) => item.sku !== product.sku);

        if (editingProductSku.value === product.sku) {
            Object.assign(productDraft, newProductDraft());
            editingProductSku.value = null;
            uploadedImages.value = [];
            productFiles.value = [];
            savedProductSnapshot.value = productSnapshot();
            activeSection.value = 'products';
            pushAdminPath('/admin');
        }

        await loadResourceRows('categories');
        message.value = payload.message || 'Producto eliminado.';
    } catch (error) {
        productErrors.value = normalizeApiErrors(error);
    } finally {
        productSaving.value = false;
    }
}

function handleFileUpload(error, file) {
    if (error) {
        productErrors.value = { upload: 'No se pudo subir la imagen.' };
        return;
    }

    if (file.serverId && !uploadedImages.value.some((image) => image.id === file.serverId)) {
        rememberUploadedImage({
            id: file.serverId,
            url: `/uploads/products/${file.serverId}`,
        });
    }

    productNotice.value = `${file.filename} subida.`;
}

function makePrimaryImage(index) {
    if (index <= 0 || index >= uploadedImages.value.length) {
        return;
    }

    const images = uploadedImages.value.slice();
    const [image] = images.splice(index, 1);
    images.unshift(image);
    uploadedImages.value = normalizedImages(images);
    productNotice.value = 'Imagen principal actualizada. Guarda el producto para persistir el cambio.';
}

function removeUploadedImage(index) {
    if (index < 0 || index >= uploadedImages.value.length) {
        return;
    }

    const removed = uploadedImages.value[index];
    uploadedImages.value = normalizedImages(uploadedImages.value.filter((_, itemIndex) => itemIndex !== index));
    productFiles.value = productFiles.value.filter((file) => file.serverId !== removed?.id);
    productNotice.value = 'Imagen eliminada del producto. Guarda para aplicar el cambio en Aimeos.';
}

function handleFileRemove(error, file) {
    if (error || !file?.serverId) {
        return;
    }

    uploadedImages.value = normalizedImages(uploadedImages.value.filter((image) => image.id !== file.serverId));
}

function rememberUploadedImage(image) {
    if (!image?.id) {
        return;
    }

    const exists = uploadedImages.value.some((item) => item.id === image.id);

    if (!exists) {
        uploadedImages.value = normalizedImages([...uploadedImages.value, image]);
    }
}

function normalizedImages(images = uploadedImages.value) {
    return images
        .filter((image) => image?.url)
        .map((image, index) => ({
            id: image.id || image.url.split('/').pop(),
            url: image.url,
            isPrimary: index === 0,
        }));
}

function uploadErrorMessage(request) {
    if (request.status === 419) {
        return 'La sesion expiro. Recarga la pagina e intenta subir la imagen otra vez.';
    }

    if (request.status === 413) {
        return 'La imagen es demasiado pesada.';
    }

    try {
        const payload = JSON.parse(request.responseText);
        return payload.message || 'No se pudo subir la imagen.';
    } catch {
        return 'No se pudo subir la imagen.';
    }
}

function productSnapshot() {
    return JSON.stringify({
        product: normalizedDraft.value,
        files: uploadedImages.value,
    });
}

function confirmLeaveDirty() {
    if (!hasUnsavedProductChanges.value) {
        return true;
    }

    return window.confirm('Hay cambios sin guardar. Si salis ahora, se van a perder.');
}

function validateProduct(product) {
    const errors = {};
    const price = Number(String(product.price).replace(',', '.'));

    if (!product.name.trim()) {
        errors.name = 'El nombre es obligatorio.';
    }

    if (!String(product.price).trim() || Number.isNaN(price) || price <= 0) {
        errors.price = 'El precio final es obligatorio y debe ser mayor a 0.';
    }

    return errors;
}

function addVariant() {
    productDraft.variants.push({
        id: createLocalId(),
        color: '',
        size: '',
        stock: 0,
    });
}

function removeVariant(index) {
    productDraft.variants.splice(index, 1);

    if (productDraft.variants.length === 0) {
        addVariant();
    }
}

function generateSku(name) {
    const base = slugPart(name) || 'PRODUCTO';
    const suffix = String(products.value.length + 1).padStart(4, '0');

    return `${base}-${suffix}`;
}

function generateBarcode(name, price) {
    const source = `${name || 'product'}-${price || '0'}`;
    const digits = Array.from(source).reduce((sum, char) => sum + char.charCodeAt(0), 0);

    return `AUTO${String(digits).padStart(8, '0')}`;
}

function slugPart(value) {
    return String(value || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-zA-Z0-9]+/g, '-')
        .replace(/^-|-$/g, '')
        .slice(0, 18)
        .toUpperCase();
}

function createLocalId() {
    return `tmp-${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;
}

function pushAdminPath(path) {
    if (window.location.pathname !== path) {
        window.history.pushState({}, '', path);
    }
}

async function syncRouteFromPath() {
    const path = window.location.pathname;
    const productMatch = path.match(/^\/admin\/product\/([^/]+)$/);

    if (productMatch) {
        const productId = decodeURIComponent(productMatch[1]);

        if (productId === 'new') {
            Object.assign(productDraft, newProductDraft());
            editingProductSku.value = null;
            uploadedImages.value = [];
        } else {
            const product = products.value.find((item) => item.sku === productId) || await loadProduct(productId);
            Object.assign(productDraft, {
                ...newProductDraft(),
                ...(product || { name: productId, sku: productId }),
                variants: product?.variants?.length ? product.variants : [{ id: createLocalId(), color: '', size: '', stock: 0 }],
            });
            editingProductSku.value = productId;
            uploadedImages.value = normalizedImages(product?.images || []);
        }

        productFiles.value = [];
        productNotice.value = '';
        productErrors.value = {};
        savedProductSnapshot.value = productSnapshot();
        activeSection.value = 'product-form';
        return;
    }

    if (!confirmLeaveDirty()) {
        pushAdminPath(editingProductSku.value ? `/admin/product/${encodeURIComponent(editingProductSku.value)}` : '/admin/product/new');
        return;
    }

    const section = path.replace(/^\/admin\/?/, '') || 'products';
    activeSection.value = navItems.some((item) => item.key === section) ? section : 'products';
}

function filterAndSortRows(rows, filters, searchableFields) {
    const query = filters.search.trim().toLowerCase();
    const [field, direction] = filters.sort.split(':');

    return rows
        .filter((row) => filters.status === 'Todos' || row.status === filters.status)
        .filter((row) => !query || searchableFields.some((key) => String(row[key] || '').toLowerCase().includes(query)))
        .slice()
        .sort((a, b) => {
            const left = normalizeSortValue(a[field]);
            const right = normalizeSortValue(b[field]);
            const result = left > right ? 1 : left < right ? -1 : 0;

            return direction === 'desc' ? -result : result;
        });
}

function normalizeSortValue(value) {
    const numeric = Number(String(value || '').replace(/[^\d.-]/g, ''));

    if (!Number.isNaN(numeric) && String(value || '').match(/\d/)) {
        return numeric;
    }

    return String(value || '').toLowerCase();
}

function formatMoney(value) {
    const number = Number(String(value || '0').replace(',', '.'));

    if (Number.isNaN(number)) {
        return '0.00';
    }

    return number.toFixed(2);
}

function totalVariantStock(variants) {
    return variants.reduce((total, variant) => total + (Number(variant.stock) || 0), 0);
}

async function loadUser() {
    await Promise.all([
        loadProducts(),
        loadSettings(),
        loadUsers(),
    ]);
}

async function loadProducts() {
    productLoading.value = true;

    try {
        const payload = await apiRequest('/admin/api/products');
        products.value = payload.data || [];
        message.value = products.value.length ? '' : 'Todavia no hay productos guardados en Aimeos.';
    } catch (error) {
        productErrors.value = normalizeApiErrors(error);
    } finally {
        productLoading.value = false;
    }
}

async function loadResourceRows(section) {
    if (!['categories', 'orders', 'payments'].includes(section)) {
        return;
    }

    resourceLoading.value = true;

    try {
        const payload = await apiRequest(`/admin/api/${section}`);
        resourceRows[section].splice(0, resourceRows[section].length, ...(payload.data || []));
    } catch (error) {
        message.value = normalizeApiErrors(error).api || `No se pudo cargar ${section}.`;
    } finally {
        resourceLoading.value = false;
    }
}

async function loadSettings() {
    try {
        const payload = await apiRequest('/admin/api/settings');
        Object.assign(shopSettings, payload.data || {});
    } catch (error) {
        message.value = normalizeApiErrors(error).api || 'No se pudo cargar la configuracion.';
    }
}

function updateSetting(key, value) {
    if (Object.prototype.hasOwnProperty.call(shopSettings, key)) {
        shopSettings[key] = value;
    }
}

async function saveSettings() {
    settingsSaving.value = true;

    try {
        const payload = await apiRequest('/admin/api/settings', {
            method: 'PUT',
            body: JSON.stringify(shopSettings),
        });
        Object.assign(shopSettings, payload.data || {});
        message.value = payload.message || 'Configuracion guardada.';
    } catch (error) {
        message.value = normalizeApiErrors(error).api || 'No se pudo guardar la configuracion.';
    } finally {
        settingsSaving.value = false;
    }
}

async function loadUsers() {
    userLoading.value = true;

    try {
        const payload = await apiRequest('/admin/api/users');
        team.value = payload.data || [];
    } catch (error) {
        message.value = normalizeApiErrors(error).api || 'No se pudieron cargar los usuarios.';
    } finally {
        userLoading.value = false;
    }
}

async function loadProduct(sku) {
    const existing = products.value.find((item) => item.sku === sku);

    try {
        const payload = await apiRequest(`/admin/api/products/${encodeURIComponent(sku)}`);
        return payload.data || existing || { name: sku, sku };
    } catch {
        return existing || { name: sku, sku };
    }
}

async function apiRequest(url, options = {}) {
    const response = await fetch(url, {
        ...options,
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
            ...(options.headers || {}),
        },
    });

    if (response.status === 401 || response.status === 419) {
        window.location.href = '/login';
        throw new Error('Login requerido.');
    }

    const payload = await response.json().catch(() => ({}));

    if (!response.ok) {
        const error = new Error(payload.message || 'No se pudo completar la operacion.');
        error.payload = payload;
        throw error;
    }

    return payload;
}

function normalizeApiErrors(error) {
    if (error.payload?.errors) {
        return Object.fromEntries(Object.entries(error.payload.errors).map(([key, values]) => [key, values[0] || 'Dato invalido.']));
    }

    return { api: error.message || 'No se pudo guardar.' };
}

onMounted(async () => {
    await loadProducts();
    await loadResourceRows('categories');
    await loadResourceRows('orders');
    await loadResourceRows('payments');
    await loadSettings();
    await loadUsers();
    await syncRouteFromPath();
    window.addEventListener('popstate', syncRouteFromPath);
    window.addEventListener('beforeunload', warnBeforeUnload);
});

onBeforeUnmount(() => {
    window.removeEventListener('popstate', syncRouteFromPath);
    window.removeEventListener('beforeunload', warnBeforeUnload);
});

function warnBeforeUnload(event) {
    if (!hasUnsavedProductChanges.value) {
        return;
    }

    event.preventDefault();
    event.returnValue = '';
}
</script>
