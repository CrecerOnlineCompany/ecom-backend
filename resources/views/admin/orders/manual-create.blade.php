@extends('admin::index')

@php
    $body_classes = '';
    $_user_ = "";
@endphp

@section('content')
<div class="container-fluid" id="manual-order-app">
    <div class="row">
        <div class="col-lg-12">
            <div class="box box-info">
                <div class="box-header with-border">
                    <h3 class="box-title">Crear Reserva Manual</h3>
                    <div class="box-tools pull-right">
                        <a href="{{ route('admin.orders.index') }}" class="btn btn-sm btn-default">
                            <i class="fa fa-arrow-left"></i> Volver a Órdenes
                        </a>
                    </div>
                </div>

                <div class="box-body">
                    <!-- Step 1: Select Movie -->
                    <div class="form-section" id="movie-selection">
                        <h4><span class="step-number">1</span> Seleccionar Película</h4>
                        <div class="form-group">
                            <label for="movie_id">Película:</label>
                            <select id="movie_id" class="form-control" style="width: 100%;">
                                <option value="">-- Seleccionar película --</option>
                            </select>
                        </div>
                    </div>

                    <!-- Step 2: Select Screening -->
                    <div class="form-section" id="screening-selection" style="display: none;">
                        <h4><span class="step-number">2</span> Seleccionar Función</h4>
                        <div class="form-group">
                            <label for="screening_id">Función:</label>
                            <select id="screening_id" class="form-control" style="width: 100%;">
                                <option value="">-- Seleccionar función --</option>
                            </select>
                        </div>
                        <div id="screening_info" class="alert alert-info" style="display: none;"></div>
                    </div>

                    <!-- Step 3: Select Seats -->
                    <div class="form-section" id="seat-selection" style="display: none;">
                        <h4><span class="step-number">3</span> Seleccionar Asientos</h4>
                        
                        <!-- Legend -->
                        <div class="seat-legend">
                            <div class="legend-item">
                                <div class="seat-box available"></div>
                                <span>Disponible</span>
                            </div>
                            <div class="legend-item">
                                <div class="seat-box selected"></div>
                                <span>Seleccionado</span>
                            </div>
                            <div class="legend-item">
                                <div class="seat-box occupied"></div>
                                <span>Vendido</span>
                            </div>
                            <div class="legend-item">
                                <div class="seat-box reserved"></div>
                                <span>Reservado</span>
                            </div>
                        </div>

                        <!-- Screen -->
                        <div class="screen">PANTALLA</div>

                        <!-- Seating Chart -->
                        <div class="seating-chart" id="seating_chart">
                            <p style="text-align: center; color: #999;">Cargando asientos...</p>
                        </div>
                    </div>

                    <!-- Step 4: Customer Info -->
                    <div class="form-section" id="product-selection" style="display: none;">
                        <h4><span class="step-number">4</span> Productos</h4>
                        <div id="product_catalog" class="products-grid">
                            <p style="text-align: center; color: #999;">Cargando productos...</p>
                        </div>
                    </div>

                    <!-- Step 5: Customer Info -->
                    <div class="form-section" id="customer-info" style="display: none;">
                        <h4><span class="step-number">5</span> Información del Cliente</h4>

                        <div class="form-group">
                            <label for="promotion_code">Código promocional (opcional):</label>
                            <input type="text" id="promotion_code" class="form-control" placeholder="Ej: PROMO2X1" />
                        </div>
                        
                        <div class="form-group">
                            <label for="customer_name">Nombre:</label>
                            <input type="text" id="customer_name" class="form-control" placeholder="Nombre del cliente" />
                        </div>

                        <div class="form-group">
                            <label for="customer_email">Email:</label>
                            <input type="email" id="customer_email" class="form-control" placeholder="Email del cliente" />
                        </div>

                        <div class="form-group">
                            <label for="customer_phone">Teléfono (opcional):</label>
                            <input type="tel" id="customer_phone" class="form-control" placeholder="Teléfono del cliente" />
                        </div>
                    </div>

                    <!-- Order Summary -->
                    <div class="panel panel-success" id="order-summary" style="display: none; margin-top: 20px;">
                        <div class="panel-heading">
                            <h3 class="panel-title">Resumen de la Orden</h3>
                        </div>
                        <div class="panel-body">
                            <table class="table table-striped">
                                <tr>
                                    <td><strong>Película:</strong></td>
                                    <td id="summary_movie">-</td>
                                </tr>
                                <tr>
                                    <td><strong>Cine / Sala:</strong></td>
                                    <td id="summary_cinema_room">-</td>
                                </tr>
                                <tr>
                                    <td><strong>Función:</strong></td>
                                    <td id="summary_time">-</td>
                                </tr>
                                <tr>
                                    <td><strong>Asientos Seleccionados:</strong></td>
                                    <td id="summary_seats">-</td>
                                </tr>
                                <tr>
                                    <td><strong>Productos:</strong></td>
                                    <td id="summary_products">-</td>
                                </tr>
                                <tr>
                                    <td><strong>Total Productos:</strong></td>
                                    <td id="summary_products_total">$0.00</td>
                                </tr>
                                <tr>
                                    <td><strong>Descuento:</strong></td>
                                    <td id="summary_discount">$0.00</td>
                                </tr>
                                <tr>
                                    <td><strong>Promociones:</strong></td>
                                    <td id="summary_promotions">-</td>
                                </tr>
                                <tr class="info">
                                    <td><strong>Total:</strong></td>
                                    <td><strong id="summary_total">$0.00</strong></td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="form-group" style="margin-top: 20px;">
                        <button type="button" id="create_order_btn" class="btn btn-success" style="display: none;">
                            <i class="fa fa-save"></i> Crear Orden
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .form-section {
        margin: 20px 0;
        padding: 15px;
        border: 1px solid #e3e3e3;
        border-radius: 4px;
        background-color: #f9f9f9;
    }

    .step-number {
        display: inline-block;
        width: 30px;
        height: 30px;
        border-radius: 50%;
        background: #5cb85c;
        color: white;
        text-align: center;
        line-height: 30px;
        margin-right: 10px;
        font-weight: bold;
    }

    .seat-legend {
        display: flex;
        gap: 30px;
        margin-bottom: 20px;
        justify-content: center;
        flex-wrap: wrap;
    }

    .legend-item {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .seat-box {
        width: 30px;
        height: 30px;
        border: 1px solid #999;
        border-radius: 4px;
    }

    .seat-box.available {
        background-color: #5cb85c;
        cursor: pointer;
    }

    .seat-box.selected {
        background-color: #0275d8;
        color: white;
    }

    .seat-box.occupied {
        background-color: #d9d9d9;
        cursor: not-allowed;
        text-decoration: line-through;
    }

    .seat-box.reserved {
        background-color: #f0ad4e;
    }

    .screen {
        text-align: center;
        margin: 30px 0 20px;
        font-size: 18px;
        font-weight: bold;
        color: #666;
        padding: 10px;
        border: 2px solid #666;
        border-radius: 4px;
    }

    .seating-chart {
        margin: 20px 0;
        padding: 20px;
        background-color: #fff;
        border: 1px solid #ddd;
        border-radius: 4px;
    }

    .seat-row {
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 10px 0;
        gap: 5px;
    }

    .row-label {
        width: 30px;
        text-align: center;
        font-weight: bold;
        color: #666;
    }

    .row-seats {
        display: flex;
        gap: 5px;
    }

    .seat {
        width: 40px;
        height: 40px;
        border: 1px solid #999;
        border-radius: 4px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        background-color: #5cb85c;
        color: white;
        font-size: 12px;
        font-weight: bold;
        transition: all 0.3s ease;
    }

    .seat:hover:not(.occupied) {
        transform: scale(1.1);
        box-shadow: 0 0 5px rgba(0, 0, 0, 0.3);
    }

    .seat.available {
        background-color: #5cb85c;
    }

    .seat.selected {
        background-color: #0275d8;
        transform: scale(1.05);
        box-shadow: 0 0 10px rgba(2, 117, 216, 0.5);
    }

    .seat.occupied {
        background-color: #d9d9d9;
        cursor: not-allowed;
        text-decoration: line-through;
        color: #666;
    }

    .seat.reserved {
        background-color: #f0ad4e;
    }

    .products-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
        gap: 12px;
    }

    .product-card {
        border: 1px solid #ddd;
        border-radius: 6px;
        background: #fff;
        padding: 10px;
        display: flex;
        gap: 10px;
        align-items: center;
    }

    .product-image {
        width: 64px;
        height: 64px;
        object-fit: cover;
        border-radius: 4px;
        border: 1px solid #eee;
        background: #f5f5f5;
    }

    .product-image-placeholder {
        width: 64px;
        height: 64px;
        border-radius: 4px;
        border: 1px solid #eee;
        background: #f5f5f5;
        color: #999;
        font-size: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        padding: 4px;
    }

    .product-info {
        flex: 1;
    }

    .product-name {
        font-weight: 600;
        margin-bottom: 2px;
    }

    .product-price {
        color: #666;
        margin-bottom: 8px;
    }

    .product-qty {
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .product-qty .btn {
        padding: 2px 8px;
    }

    .product-qty input {
        width: 56px;
        text-align: center;
    }
</style>

<script>
(function () {
    function bootManualOrderApp() {
        const root = document.getElementById('manual-order-app');
        if (!root || root.dataset.initialized === '1') {
            return;
        }

        root.dataset.initialized = '1';

        const app = {
            csrfToken: '{{ csrf_token() }}',
            baseUrl: '/admin/orders/manual',
            selectedSeats: new Map(),
            selectedProducts: new Map(),
            availableProducts: [],
            currentScreening: null,
            currentMovie: null,
            pricingRequestSeq: 0,
            pricingPreviewTimer: null,

            async init() {
                this.loadMovies();
                this.setupEventListeners();
            },

            setupEventListeners() {
                document.getElementById('movie_id').addEventListener('change', () => this.onMovieSelected());
                document.getElementById('screening_id').addEventListener('change', () => this.onScreeningSelected());
                document.getElementById('create_order_btn').addEventListener('click', () => this.createOrder());
                document.getElementById('promotion_code').addEventListener('input', () => this.schedulePricingPreview());
            },

        async loadMovies() {
            try {
                const response = await fetch(`${this.baseUrl}/api/movies`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': this.csrfToken,
                        'Content-Type': 'application/json',
                    },
                });

                const result = await response.json();
                if (result.success) {
                    this.populateMovies(result.data);
                }
            } catch (error) {
                console.error('Error loading movies:', error);
                alert('Error al cargar películas: ' + error.message);
            }
        },

        populateMovies(movies) {
            const select = document.getElementById('movie_id');
            movies.forEach(movie => {
                const option = document.createElement('option');
                option.value = movie.id;
                option.textContent = movie.title;
                select.appendChild(option);
            });
        },

            async onMovieSelected() {
            const movieId = document.getElementById('movie_id').value;
            if (!movieId) {
                document.getElementById('screening-selection').style.display = 'none';
                document.getElementById('seat-selection').style.display = 'none';
                document.getElementById('product-selection').style.display = 'none';
                document.getElementById('customer-info').style.display = 'none';
                document.getElementById('order-summary').style.display = 'none';
                return;
            }

            this.currentMovie = movieId;
            this.selectedSeats = new Map();
            this.selectedProducts = new Map();

            try {
                const response = await fetch(`${this.baseUrl}/api/screenings`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': this.csrfToken,
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ movie_id: movieId }),
                });

                const result = await response.json();
                if (result.success) {
                    this.populateScreenings(result.data);
                    document.getElementById('screening-selection').style.display = 'block';
                } else {
                    alert('Error: ' + (result.message || 'Error al cargar funciones'));
                }
            } catch (error) {
                console.error('Error loading screenings:', error);
                alert('Error al cargar funciones: ' + error.message);
            }
        },

        populateScreenings(screenings) {
            const select = document.getElementById('screening_id');
            select.innerHTML = '<option value="">-- Seleccionar función --</option>';
            
            screenings.forEach(screening => {
                const option = document.createElement('option');
                option.value = screening.id;
                option.textContent = `${screening.cinema_name} - ${screening.room_name} - ${screening.start_time} (${screening.format})`;
                option.dataset.price = screening.price;
                select.appendChild(option);
            });
        },

            async onScreeningSelected() {
            const screeningId = document.getElementById('screening_id').value;
            if (!screeningId) {
                document.getElementById('seat-selection').style.display = 'none';
                document.getElementById('product-selection').style.display = 'none';
                document.getElementById('customer-info').style.display = 'none';
                document.getElementById('order-summary').style.display = 'none';
                return;
            }

            this.currentScreening = parseInt(screeningId);
            this.selectedSeats = new Map();
            this.selectedProducts = new Map();

            try {
                console.log('Fetching seating chart for screening:', screeningId);
                const response = await fetch(`${this.baseUrl}/api/seating-chart`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': this.csrfToken,
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ screening_id: screeningId }),
                });

                console.log('Response status:', response.status);
                const result = await response.json();
                
                if (!response.ok) {
                    console.error('API Error response:', result);
                    alert('Error: ' + (result.message || 'Error al cargar asientos'));
                    return;
                }
                
                if (result.success) {
                    this.renderSeatingChart(result.data);
                    this.updateScreeningInfo(result.data);
                    await this.loadProducts();
                    document.getElementById('seat-selection').style.display = 'block';
                    document.getElementById('product-selection').style.display = 'block';
                    document.getElementById('customer-info').style.display = 'block';
                } else {
                    console.error('API Success false:', result);
                    alert('Error: ' + (result.message || 'Error desconocido'));
                }
            } catch (error) {
                console.error('Error loading seats:', error);
                alert('Error al cargar asientos: ' + error.message);
            }
        },

            async loadProducts() {
            const catalog = document.getElementById('product_catalog');
            catalog.innerHTML = '<p style="text-align: center; color: #999;">Cargando productos...</p>';

            try {
                const response = await fetch(`${this.baseUrl}/api/products`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': this.csrfToken,
                        'Content-Type': 'application/json',
                    },
                });

                const result = await response.json();
                if (!response.ok || !result.success) {
                    throw new Error(result.message || 'No se pudo cargar el catálogo');
                }

                this.availableProducts = Array.isArray(result.data) ? result.data : [];
                this.renderProductCatalog(this.availableProducts);
            } catch (error) {
                console.error('Error loading products:', error);
                catalog.innerHTML = '<p style="text-align: center; color: #999;">No se pudieron cargar los productos.</p>';
            }
        },

            renderProductCatalog(products) {
            const catalog = document.getElementById('product_catalog');
            if (!Array.isArray(products) || products.length === 0) {
                catalog.innerHTML = '<p style="text-align: center; color: #999;">No hay productos activos.</p>';
                this.updateOrderSummary();
                return;
            }

            let html = '';
            products.forEach((product) => {
                const code = String(product.code || '');
                const name = String(product.name || code);
                const imageUrl = product.image_url ? String(product.image_url) : '';
                const unitPrice = Number(product.unit_price || 0);
                const qty = this.selectedProducts.get(code)?.quantity || 0;
                const safeName = name.replace(/"/g, '&quot;');

                html += `
                    <div class="product-card" data-product-code="${code}" data-product-name="${safeName}" data-product-price="${unitPrice}">
                        ${imageUrl
                            ? `<img src="${imageUrl}" alt="${safeName}" class="product-image">`
                            : '<div class="product-image-placeholder">Sin imagen</div>'}
                        <div class="product-info">
                            <div class="product-name">${name}</div>
                            <div class="product-price">$${unitPrice.toFixed(2)}</div>
                            <div class="product-qty">
                                <button type="button" class="btn btn-default btn-xs product-minus">-</button>
                                <input type="number" class="form-control input-sm product-qty-input" min="0" value="${qty}">
                                <button type="button" class="btn btn-default btn-xs product-plus">+</button>
                            </div>
                        </div>
                    </div>
                `;
            });

            catalog.innerHTML = html;

            catalog.querySelectorAll('.product-card').forEach((card) => {
                const code = String(card.dataset.productCode || '');
                const name = String(card.dataset.productName || code);
                const unitPrice = Number(card.dataset.productPrice || 0);
                const input = card.querySelector('.product-qty-input');
                const minus = card.querySelector('.product-minus');
                const plus = card.querySelector('.product-plus');

                minus.addEventListener('click', () => {
                    const next = Math.max(0, Number(input.value || 0) - 1);
                    input.value = String(next);
                    this.setProductQuantity(code, name, unitPrice, next);
                });

                plus.addEventListener('click', () => {
                    const next = Math.max(0, Number(input.value || 0) + 1);
                    input.value = String(next);
                    this.setProductQuantity(code, name, unitPrice, next);
                });

                input.addEventListener('change', () => {
                    const next = Math.max(0, Number(input.value || 0));
                    input.value = String(next);
                    this.setProductQuantity(code, name, unitPrice, next);
                });
            });

            this.updateOrderSummary();
        },

            setProductQuantity(code, name, unitPrice, quantity) {
            if (quantity <= 0) {
                this.selectedProducts.delete(code);
            } else {
                this.selectedProducts.set(code, {
                    code,
                    name,
                    unit_price: Number.isFinite(unitPrice) ? unitPrice : 0,
                    quantity: Number(quantity),
                });
            }

            this.updateOrderSummary();
        },

            getSelectedProductsPayload() {
            return Array.from(this.selectedProducts.values()).map((item) => ({
                code: item.code,
                quantity: Number(item.quantity || 0),
            })).filter((item) => item.quantity > 0);
        },

            getRowLabel(rowNum) {
                return String(rowNum ?? '-');
            },

            renderSeatingChart(data) {
                const chart = document.getElementById('seating_chart');
                const seats = data.seats || {};
                const rowEntries = Object.entries(seats).sort((a, b) => Number(a[0]) - Number(b[0]));

                if (rowEntries.length === 0) {
                    chart.innerHTML = '<p style="text-align: center; color: #999;">No hay asientos para mostrar.</p>';
                    return;
                }

                let html = '';
                for (const [rowNum, rowSeats] of rowEntries) {
                    const normalizedRowSeats = (Array.isArray(rowSeats) ? rowSeats : Object.values(rowSeats || {}))
                        .sort((a, b) => Number(a.seat_number) - Number(b.seat_number));

                    html += '<div class="seat-row">';
                    html += `<div class="row-label">${this.getRowLabel(rowNum)}</div>`;
                    html += '<div class="row-seats">';

                    normalizedRowSeats.forEach((seat) => {
                        const status = String(seat.status || 'available');
                        const seatClass = status === 'sold'
                            ? 'occupied'
                            : (status === 'reserved' ? 'reserved' : 'available');
                        const seatId = Number(seat.id);
                        const seatCode = String(seat.seat_code || '');
                        const selectedClass = this.selectedSeats.has(seatId) ? ' selected' : '';

                        const seatPrice = Number(seat.price || 0);
                        const seatLabel = seatCode !== '' ? seatCode : String(seat.seat_number ?? '');
                        html += `<div class="seat ${seatClass}${selectedClass}" data-seat-id="${seatId}" data-seat-code="${seatLabel}" data-seat-price="${seatPrice}">${seatLabel}</div>`;
                    });

                    html += '</div></div>';
                }

                chart.innerHTML = html;

                document.querySelectorAll('.seating-chart .seat').forEach((seatEl) => {
                    seatEl.addEventListener('click', () => this.toggleSeat(seatEl));
                });
            },

            toggleSeat(seatEl) {
                if (seatEl.classList.contains('occupied') || seatEl.classList.contains('reserved')) {
                    return;
                }

                const seatId = Number(seatEl.dataset.seatId);
                const seatCode = String(seatEl.dataset.seatCode || '');
                const seatPrice = Number(seatEl.dataset.seatPrice || 0);

                if (!Number.isFinite(seatId) || seatId <= 0) {
                    return;
                }

                if (this.selectedSeats.has(seatId)) {
                    this.selectedSeats.delete(seatId);
                    seatEl.classList.remove('selected');
                    seatEl.classList.add('available');
                } else {
                    this.selectedSeats.set(seatId, {
                        code: seatCode,
                        price: Number.isFinite(seatPrice) ? seatPrice : 0,
                    });
                    seatEl.classList.remove('available');
                    seatEl.classList.add('selected');
                }

                this.updateOrderSummary();
            },

        updateScreeningInfo(data) {
            const movieTitle = document.getElementById('movie_id').options[document.getElementById('movie_id').selectedIndex].text;
            document.getElementById('summary_movie').textContent = movieTitle;
            document.getElementById('summary_cinema_room').textContent = `${data.cinema_name} / ${data.room_name}`;
            document.getElementById('summary_time').textContent = data.screening_start_time;
        },

            updateOrderSummary() {
            if (this.selectedSeats.size === 0) {
                document.getElementById('order-summary').style.display = 'none';
                document.getElementById('create_order_btn').style.display = 'none';
                return;
            }

            const selectedSeatValues = Array.from(this.selectedSeats.values());
            const seatTotal = selectedSeatValues.reduce((acc, seatData) => {
                if (seatData && typeof seatData === 'object') {
                    return acc + Number(seatData.price || 0);
                }
                return acc;
            }, 0);
            const selectedProducts = Array.from(this.selectedProducts.values());
            const productsTotal = selectedProducts.reduce((acc, item) => {
                const price = Number(item.unit_price || 0);
                const qty = Number(item.quantity || 0);
                return acc + (price * qty);
            }, 0);
            const total = seatTotal + productsTotal;

            const seatCodes = selectedSeatValues.map((seatData) => {
                if (seatData && typeof seatData === 'object') {
                    return String(seatData.code || '');
                }
                return String(seatData || '');
            });
            const productLines = selectedProducts.map((item) => `${item.name} x${item.quantity}`);

            document.getElementById('summary_seats').textContent = seatCodes.join(', ');
            document.getElementById('summary_products').textContent = productLines.length > 0 ? productLines.join(', ') : '-';
            document.getElementById('summary_products_total').textContent = `$${productsTotal.toFixed(2)}`;
            document.getElementById('summary_discount').textContent = '$0.00';
            document.getElementById('summary_promotions').textContent = '-';
            document.getElementById('summary_total').textContent = `$${total.toFixed(2)}`;
            document.getElementById('order-summary').style.display = 'block';
            document.getElementById('create_order_btn').style.display = 'inline-block';
            this.schedulePricingPreview();
            },

            schedulePricingPreview() {
                if (this.pricingPreviewTimer) {
                    clearTimeout(this.pricingPreviewTimer);
                }

                this.pricingPreviewTimer = setTimeout(() => {
                    this.pricingPreviewTimer = null;
                    this.refreshPricingPreview();
                }, 200);
            },

            async refreshPricingPreview() {
                if (!this.currentScreening || this.selectedSeats.size === 0) {
                    return;
                }

                const requestSeq = ++this.pricingRequestSeq;
                const promotionCode = document.getElementById('promotion_code').value.trim();
                const customerEmail = document.getElementById('customer_email').value.trim();

                try {
                    const response = await fetch(`${this.baseUrl}/api/pricing-preview`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': this.csrfToken,
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            screening_id: this.currentScreening,
                            seat_ids: Array.from(this.selectedSeats.keys()),
                            products: this.getSelectedProductsPayload(),
                            promotion_code: promotionCode || null,
                            customer_email: customerEmail || null,
                        }),
                    });

                    const result = await response.json();
                    if (requestSeq !== this.pricingRequestSeq) {
                        return;
                    }

                    if (!response.ok || !result.success) {
                        if (result.message) {
                            document.getElementById('summary_promotions').textContent = result.message;
                        }
                        return;
                    }

                    const totalDiscount = Number(result.total_discount || 0);
                    const totalPrice = Number(result.total_price || 0);
                    const appliedPromotions = Array.isArray(result.applied_promotions) ? result.applied_promotions : [];
                    const promotionNames = appliedPromotions
                        .map((promo) => String(promo.name || promo.code || '').trim())
                        .filter((label) => label !== '');

                    document.getElementById('summary_discount').textContent = `-$${totalDiscount.toFixed(2)}`;
                    document.getElementById('summary_promotions').textContent = promotionNames.length > 0
                        ? promotionNames.join(', ')
                        : '-';
                    document.getElementById('summary_total').textContent = `$${totalPrice.toFixed(2)}`;
                } catch (error) {
                    console.error('Error en pricing preview manual:', error);
                }
            },

            async createOrder() {
            const customerEmail = document.getElementById('customer_email').value.trim();
            const customerName = document.getElementById('customer_name').value.trim();
            const customerPhone = document.getElementById('customer_phone').value.trim();
            const promotionCode = document.getElementById('promotion_code').value.trim();

            if (!customerEmail) {
                alert('Por favor ingresa el email del cliente');
                return;
            }

            if (!customerName) {
                alert('Por favor ingresa el nombre del cliente');
                return;
            }

            if (this.selectedSeats.size === 0) {
                alert('Por favor selecciona al menos un asiento');
                return;
            }

            const btn = document.getElementById('create_order_btn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Creando...';

            try {
                const response = await fetch(`${this.baseUrl}/api/store`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': this.csrfToken,
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        screening_id: this.currentScreening,
                        seat_ids: Array.from(this.selectedSeats.keys()),
                        products: this.getSelectedProductsPayload(),
                        promotion_code: promotionCode || null,
                        customer_email: customerEmail,
                        customer_name: customerName,
                        customer_phone: customerPhone || null,
                    }),
                });

                const result = await response.json();

                if (result.success) {
                    alert(result.message);
                    window.location.href = result.redirect_url;
                } else {
                    alert('Error: ' + result.message);
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa fa-save"></i> Crear Orden';
                }
            } catch (error) {
                console.error('Error creating order:', error);
                alert('Error al crear la orden: ' + error.message);
                btn.disabled = false;
                btn.innerHTML = '<i class="fa fa-save"></i> Crear Orden';
            }
            },
        };

        app.init();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootManualOrderApp);
    } else {
        bootManualOrderApp();
    }

    if (window.jQuery) {
        window.jQuery(document)
            .off('pjax:end.manualOrderApp')
            .on('pjax:end.manualOrderApp', function () {
                setTimeout(bootManualOrderApp, 0);
            });
    }
})();
</script>
@endsection
