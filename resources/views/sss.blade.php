@extends('layouts.app')

@section('title', 'Stellar Synthesis Showcase (SSS)')
@section('meta_description', 'Explore a live gallery of generative 3D badges, AI-created art, and the creative pulse of the GOAT.uz community.')
@section('canonical_url', route('sss.show'))

@section('content')
    <div x-data="{ isModalOpen: false, selectedBadge: null }">
        {{-- Main Page Header --}}
        <div class="text-center pt-4 sm:pt-8 pb-12">
            <h1 class="text-4xl sm:text-5xl font-bold tracking-tight text-gray-800">Stellar Synthesis Showcase</h1>
            <p class="mt-4 max-w-2xl mx-auto text-lg text-gray-500">A live gallery of generative 3D badges, AI-created
                art, and the creative pulse of our community.</p>
        </div>

        {{-- Section 1: The Genesis Collection (Badges) --}}
        <section class="mb-16">
            <div class="text-center mb-8">
                <h2 class="text-3xl font-bold text-gray-800">The Genesis Collection</h2>
                <p class="text-gray-500 mt-1">Explore our community's highest honors. Click a badge to see its
                    story.</p>
            </div>

            {{-- SCALABLE BADGE GRID --}}
            <div class="grid grid-cols-2 gap-4">
                @foreach($badges as $badge)
                    <div @click="selectedBadge = {{ json_encode($badge) }}; isModalOpen = true"
                         class="bg-white rounded-lg shadow-[inset_0_0_0_0.5px_rgba(0,0,0,0.2)] aspect-square flex flex-col items-center justify-center p-4 text-center cursor-pointer transition-all duration-300 hover:shadow-xl hover:-translate-y-1">

                        {{-- Canvas for 3D Badge --}}
                        <div class="w-full h-3/4">
                            <canvas class="badge-showcase-canvas" data-badge-key="{{ $badge->key }}"></canvas>
                        </div>

                        {{-- Badge Title --}}
                        <h3 class="mt-2 font-semibold text-gray-800 text-sm">{{ $badge->title }}</h3>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- OTHER SECTIONS (Pantheon, Dream Engine, etc.) CAN GO HERE --}}


        {{-- ADVANCED BADGE DETAIL MODAL --}}
        <div x-show="isModalOpen"
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black bg-opacity-50"
             @click.away="isModalOpen = false"
             style="display: none;">

            <div class="bg-white rounded-xl shadow-2xl w-full max-w-md mx-auto p-6" @click.stop>
                {{-- Modal Header --}}
                <div class="flex items-center justify-between mb-4">
                    <h3 x-text="selectedBadge?.title" class="text-2xl font-bold text-blue-800"></h3>
                    <button @click="isModalOpen = false" class="text-gray-400 hover:text-gray-600">&times;</button>
                </div>

                {{-- Modal Body --}}
                <div class="space-y-4">
                    <p x-text="selectedBadge?.description" class="text-gray-600"></p>

                    {{-- Stats Grid --}}
                    <div class="grid grid-cols-3 gap-4 text-center py-4 bg-gray-50 rounded-lg">
                        <div>
                            <div class="text-xs text-gray-500 uppercase tracking-wider">Rarity</div>
                            <div x-text="selectedBadge?.stats.rarity" class="font-semibold text-gray-800"></div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500 uppercase tracking-wider">Origin</div>
                            <div x-text="selectedBadge?.stats.origin" class="font-semibold text-gray-800"></div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500 uppercase tracking-wider">Type</div>
                            <div x-text="selectedBadge?.stats.type" class="font-semibold text-gray-800"></div>
                        </div>
                    </div>

                    {{-- Rarity Bar --}}
                    <div>
                        <div class="flex justify-between items-center text-xs text-gray-500 mb-1">
                            <span x-text="`Held by ${selectedBadge?.rarity_percentage}% of users`"></span>
                            <span x-text="selectedBadge?.stats.rarity"></span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-gradient-to-r from-indigo-500 via-purple-500 to-pink-500 h-2 rounded-full"
                                 :style="`width: ${selectedBadge?.rarity_percentage}%`"></div>
                        </div>
                    </div>

                    {{-- Holders --}}
                    <div>
                        <h4 class="text-sm font-semibold text-gray-700 mb-2">Notable Holders</h4>
                        <div class="flex items-center space-x-2">
                            <template x-for="holder in selectedBadge?.holders" :key="holder.username">
                                <a href="#" :title="holder.username">
                                    <img :src="holder.profile_picture" :alt="holder.username"
                                         class="w-8 h-8 rounded-full object-cover border-2 border-white ring-1 ring-gray-300">
                                </a>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
