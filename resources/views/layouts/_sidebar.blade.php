<?php
// File: resources/views/layouts/_sidebar.blade.php
?>
<aside class="navbar navbar-vertical navbar-expand-lg navbar-dark">
     <div class="container">
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbar-menu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <!-- Bagian Logo & Judul -->
        <div class="w-100 text-center my-3">
            <a href="{{ route('admin.dashboard') }}" class="text-decoration-none">
                <img src="{{ asset('image/logo-mmi.png') }}"
                    alt="Logo MMI"
                    style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 2px solid #fff; background-color: #fff;">
                <h3 class="font-weight-bold mt-2 mb-0 text-white">WAREHOUSE MMI</h3>
            </a>
        </div>
        <div class="collapse navbar-collapse" id="navbar-menu">
            <ul class="navbar-nav pt-lg-3">
                <div class="hr-text hr-text-left ml-2 mb-2 mt-2">Dashboard</div>
                @hasanyrole('admin|super admin')
                    <li class="nav-item">
                        <a class="nav-link {{ Route::is('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">
                             <span class="nav-link-icon d-md-none d-lg-inline-block mr-1">
                                <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-apps"
                                    width="24" height="24" viewBox="0 0 24 24" stroke-width="2"
                                    stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                                    <rect x="4" y="4" width="6" height="6" rx="1">
                                    </rect>
                                    <rect x="4" y="14" width="6" height="6" rx="1">
                                    </rect>
                                    <rect x="14" y="14" width="6" height="6" rx="1">
                                    </rect>
                                    <line x1="14" y1="7" x2="20" y2="7"></line>
                                    <line x1="17" y1="4" x2="17" y2="10"></line>
                                </svg>
                            </span>
                            <span class="nav-link-title">
                                Dashboard
                            </span>
                        </a>
                    </li>                  
                   <div class="hr-text hr-text-left ml-2 mb-2 mt-2">Menu</div>
                    <li class="nav-item">
                        <a class="nav-link {{ Route::is('admin.supplier*') ? 'active' : '' }}" href="{{ route('admin.supplier.index') }}">
                            <span class="nav-link-icon d-md-none d-lg-inline-block mr-1">
                            <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-truck"
                            width="24" height="24" viewBox="0 0 24 24" stroke-width="2"
                            stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                            <circle cx="7" cy="17" r="2"></circle>
                            <circle cx="17" cy="17" r="2"></circle>
                            <path d="M5 17h-2v-11a1 1 0 0 1 1 -1h9v12m-4 0h6m4 0h2v-6h-8m0 -5h5l3 5"></path>
                            </svg>
                            </span>
                            <span class="nav-link-title">
                                    Supplier
                            </span>
                        </a>
                    </li>
                   
                     <li class="nav-item">
                        <a class="nav-link {{ Route::is('admin.stock*') ? 'active' : '' }}" href="{{ route('admin.stock.index') }}">
                            <span class="nav-link-icon d-md-none d-lg-inline-block mr-1">
                                <svg xmlns="http://www.w3.org/2000/svg"
                                    class="icon icon-tabler icon-tabler-clipboard-list" width="24" height="24"
                                    viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"
                                    stroke-linecap="round" stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                                    <path
                                        d="M9 5h-2a2 2 0 0 0 -2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-12a2 2 0 0 0 -2 -2h-2">
                                    </path>
                                    <rect x="9" y="3" width="6" height="4" rx="2">
                                    </rect>
                                    <line x1="9" y1="12" x2="9.01" y2="12"></line>
                                    <line x1="13" y1="12" x2="15" y2="12"></line>
                                    <line x1="9" y1="16" x2="9.01" y2="16"></line>
                                    <line x1="13" y1="16" x2="15" y2="16"></line>
                                </svg>
                            </span>
                            <span class="nav-link-title">
                                Stok Barang
                            </span>
                        </a>
                    </li>

                     {{-- Buyer (FOB) --}}
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('admin.buyers.*') ? 'active' : '' }}"
                           href="{{ route('admin.buyers.index') }}">
                            <span class="nav-link-icon d-md-none d-lg-inline-block mr-1">
                                <svg xmlns="http://www.w3.org/2000/svg"
                                     class="icon icon-tabler icon-tabler-user-circle" width="24" height="24"
                                     viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"
                                     stroke-linecap="round" stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                    <circle cx="12" cy="12" r="9" />
                                    <circle cx="12" cy="10" r="3" />
                                    <path d="M6 18c1.5 -2 3.5 -3 6 -3s4.5 1 6 3" />
                                </svg>
                            </span>
                            <span class="nav-link-title">
                                Buyer (FOB)
                            </span>
                        </a>
                    </li>

                    {{-- STOK FOB (BUYER) BARU --}}
                    <li class="nav-item">
                        <a class="nav-link {{ Route::is('admin.fob-stocks*') ? 'active' : '' }}" href="{{ route('admin.fob-stocks.index') }}">
                            <span class="nav-link-icon d-md-none d-lg-inline-block mr-1">
                                <svg xmlns="http://www.w3.org/2000/svg"
                                    class="icon icon-tabler icon-tabler-box" width="24" height="24"
                                    viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"
                                    stroke-linecap="round" stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                                    <polyline points="12 3 20 7 12 11 4 7 12 3"></polyline>
                                    <polyline points="4 7 4 17 12 21 20 17 20 7"></polyline>
                                    <line x1="12" y1="11" x2="12" y2="21"></line>
                                </svg>
                            </span>
                            <span class="nav-link-title">
                                Stok FOB 
                            </span>
                        </a>
                    </li>

                    <div class="hr-text hr-text-left ml-2 mb-2 mt-2">Transaksi</div>
                    <li class="nav-item">
                        <a class="nav-link {{ Route::is('admin.purchase-orders*') ? 'active' : '' }}" href="{{ route('admin.purchase-orders.index') }}">
                            <span class="nav-link-icon d-md-none d-lg-inline-block mr-1">
                                <svg xmlns="http://www.w3.org/2000/svg"
                                    class="icon icon-tabler icon-tabler-shopping-cart-plus" width="24" height="24"
                                    viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"
                                    stroke-linecap="round" stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                                    <circle cx="6" cy="19" r="2"></circle>
                                    <circle cx="17" cy="19" r="2"></circle>
                                    <path d="M17 17h-11v-14h-2"></path>
                                    <path d="M6 5l6.005 .429m7.138 6.573l-.143 .998h-13"></path>
                                    <path d="M15 6h6m-3 -3v6"></path>
                                </svg>
                            </span>
                            <span class="nav-link-title">
                                Purchase Orders
                            </span>
                        </a>
                    </li>
                    <div class="hr-text hr-text-left ml-2 mb-2 mt-2">Produksi</div>
                    <li class="nav-item">
                        <a class="nav-link {{ Route::is('admin.order*') ? 'active' : '' }}" href="{{ route('admin.orders.index') }}">
                            <span class="nav-link-icon d-md-none d-lg-inline-block mr-1">
                                <svg xmlns="http://www.w3.org/2000/svg"
                                    class="icon icon-tabler icon-tabler-shopping-cart-plus" width="24" height="24"
                                    viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"
                                    stroke-linecap="round" stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                                    <circle cx="6" cy="19" r="2"></circle>
                                    <circle cx="17" cy="19" r="2"></circle>
                                    <path d="M17 17h-11v-14h-2"></path>
                                    <path d="M6 5l6.005 .429m7.138 6.573l-.143 .998h-13"></path>
                                    <path d="M15 6h6m-3 -3v6"></path>
                                </svg>
                            </span>
                            <span class="nav-link-title">
                                Permintaan Barang
                            </span>
                        </a>
                    </li>
                    <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.outgoing.*') ? 'active' : '' }}"
                        href="{{ route('admin.outgoing.index') }}">
                        <span class="nav-link-icon d-md-none d-lg-inline-block mr-1">
                        <svg xmlns="http://www.w3.org/2000/svg"
                            class="icon icon-tabler icon-tabler-shopping-cart-x" width="24" height="24"
                            viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"
                            stroke-linecap="round" stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                            <circle cx="6" cy="19" r="2"></circle>
                            <circle cx="17" cy="19" r="2"></circle>
                            <path d="M17 17h-11v-14h-2"></path>
                            <path d="M6 5l7.999 .571m5.43 4.43l-.429 2.999h-13"></path>
                            <path d="M17 3l4 4"></path>
                            <path d="M21 3l-4 4"></path>
                        </svg>
                        </span>
                        <span class="nav-link-title">
                        Barang Keluar
                        </span>
                    </a>
                    </li>

                    {{--<li class="nav-item">
                        <a class="nav-link {{ Route::is('admin.permission*') ? 'active' : '' }}" href="{{ route('admin.permission.index') }}">
                            <span class="nav-link-title">Permission</span>
                        </a>
                    </li> 

                    <li class="nav-item">
                        <a class="nav-link {{ Route::is('admin.role*') ? 'active' : '' }}" href="{{ route('admin.role.index') }}">
                            <span class="nav-link-title">Role</span>
                        </a>
                    </li> --}}

                      <div class="hr-text hr-text-left ml-2 mb-2 mt-2">Laporan</div>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('admin.reports.*') ? 'active' : '' }}"
                                href="{{ route('admin.reports.index') }}">
                                <span class="nav-link-icon d-md-none d-lg-inline-block mr-1">
                                <svg xmlns="http://www.w3.org/2000/svg"
                                    class="icon icon-tabler icon-tabler-file-analytics" width="24" height="24"
                                    viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"
                                    stroke-linecap="round" stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                    <path d="M14 3v4a1 1 0 0 0 1 1h4"/>
                                    <path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z"/>
                                    <line x1="9" y1="17" x2="9" y2="12"/>
                                    <line x1="12" y1="17" x2="12" y2="16"/>
                                    <line x1="15" y1="17" x2="15" y2="14"/>
                                </svg>
                                </span>
                                <span class="nav-link-title">Laporan</span>
                            </a>
                            </li>
                            
                     <div class="hr-text hr-text-left ml-2 mb-2 mt-2">User Manajemen</div>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.setting.index') }}">
                            <span class="nav-link-icon d-md-none d-lg-inline-block mr-1">
                                <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-settings"
                                    width="24" height="24" viewBox="0 0 24 24" stroke-width="2"
                                    stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                                    <path
                                        d="M10.325 4.317c.426 -1.756 2.924 -1.756 3.35 0a1.724 1.724 0 0 0 2.573 1.066c1.543 -.94 3.31 .826 2.37 2.37a1.724 1.724 0 0 0 1.065 2.572c1.756 .426 1.756 2.924 0 3.35a1.724 1.724 0 0 0 -1.066 2.573c.94 1.543 -.826 3.31 -2.37 2.37a1.724 1.724 0 0 0 -2.572 1.065c-.426 1.756 -2.924 1.756 -3.35 0a1.724 1.724 0 0 0 -2.573 -1.066c-1.543 .94 -3.31 -.826 -2.37 -2.37a1.724 1.724 0 0 0 -1.065 -2.572c-1.756 -.426 -1.756 -2.924 0 -3.35a1.724 1.724 0 0 0 1.066 -2.573c-.94 -1.543 .826 -3.31 2.37 -2.37c1 .608 2.296 .07 2.572 -1.065z">
                                    </path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                            </span>
                            <span class="nav-link-title">
                                Akun
                            </span>
                        </a>
                    </li>
                @endhasanyrole
            </ul>
        </div>
    </div>
</aside>
