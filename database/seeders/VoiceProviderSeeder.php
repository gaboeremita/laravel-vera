<?php

namespace Database\Seeders;

use App\Enums\VoiceProviderFormat;
use App\Models\VoiceProvider;
use Illuminate\Database\Seeder;

class VoiceProviderSeeder extends Seeder
{
    public function run(): void
    {
        $orpheus = VoiceProvider::updateOrCreate(
            ['name' => 'Orpheus'],
            [
                'url' => 'http://127.0.0.1:5005/v1/audio/speech',
                'format' => VoiceProviderFormat::OpenAiCompatible,
                'instructions' => "Start two local services before selecting this model:\n\n"
                    ."1. llama-server -m <path-to-orpheus.gguf> --host 127.0.0.1 --port 8081 -c 8192\n"
                    ."2. cd Orpheus-FastAPI && source venv/bin/activate && python app.py   # serves :5005\n\n"
                    .'Full setup: README → Voice Mode.',
            ]
        );

        $orpheus->models()->updateOrCreate(
            ['endpoint' => 'orpheus'],
            [
                'name' => 'Orpheus 3B',
                'voices' => ['tara', 'leah', 'jess', 'leo', 'dan', 'mia', 'zac', 'zoe'],
                'config' => ['timeout' => 120],
            ]
        );

        $kitten = VoiceProvider::updateOrCreate(
            ['name' => 'KittenTTS'],
            [
                'url' => 'http://127.0.0.1:8005/v1/audio/speech',
                'format' => VoiceProviderFormat::OpenAiCompatible,
                'instructions' => "Start the server before selecting this model:\n\n"
                    ."brew install espeak-ng\n"
                    ."cd Kitten-TTS-Server && source venv/bin/activate && python server.py   # serves :8005\n\n"
                    ."To switch which KittenTTS model is loaded (Nano/Micro/Mini), use the wrapper's own UI:\n"
                    .'http://127.0.0.1:8005',
            ]
        );

        $kitten->models()->updateOrCreate(
            ['endpoint' => 'kitten-tts'],
            [
                'name' => 'Kitten TTS',
                'voices' => ['Bella', 'Jasper', 'Luna', 'Bruno', 'Rosie', 'Hugo', 'Kiki', 'Leo'],
                'config' => ['timeout' => 30],
            ]
        );

        $openaiTts = VoiceProvider::updateOrCreate(
            ['name' => 'OpenAI TTS'],
            [
                'url' => 'https://api.openai.com/v1/audio/speech',
                'format' => VoiceProviderFormat::OpenAiTts,
                'api_key' => env('OPENAI_API_KEY'),
                'instructions' => 'Requires an OpenAI API key. Add OPENAI_API_KEY to your .env file.',
            ]
        );

        $openaiTts->models()->updateOrCreate(
            ['endpoint' => 'gpt-4o-mini-tts'],
            [
                'name' => 'GPT-4o Mini TTS',
                'voices' => ['alloy', 'ash', 'ballad', 'coral', 'echo', 'fable', 'nova', 'onyx', 'sage', 'shimmer', 'verse', 'marin', 'cedar'],
                'config' => ['timeout' => 30],
            ]
        );

        $deepgram = VoiceProvider::updateOrCreate(
            ['name' => 'Deepgram'],
            [
                'url' => 'https://api.deepgram.com',
                'format' => VoiceProviderFormat::Deepgram,
                'api_key' => env('DEEPGRAM_API_KEY'),
                'instructions' => 'Requires a Deepgram API key. Add DEEPGRAM_API_KEY to your .env file.',
            ]
        );

        $deepgram->models()->updateOrCreate(
            ['name' => 'Aura 2'],
            [
                'endpoint' => 'v1/speak',
                'voices' => [
                    // English
                    'aura-2-thalia-en', 'aura-2-andromeda-en', 'aura-2-helena-en', 'aura-2-apollo-en',
                    'aura-2-arcas-en', 'aura-2-aries-en', 'aura-2-amalthea-en', 'aura-2-asteria-en',
                    'aura-2-athena-en', 'aura-2-atlas-en', 'aura-2-aurora-en', 'aura-2-callista-en',
                    'aura-2-cora-en', 'aura-2-cordelia-en', 'aura-2-delia-en', 'aura-2-draco-en',
                    'aura-2-electra-en', 'aura-2-harmonia-en', 'aura-2-hera-en', 'aura-2-hermes-en',
                    'aura-2-hyperion-en', 'aura-2-iris-en', 'aura-2-janus-en', 'aura-2-juno-en',
                    'aura-2-jupiter-en', 'aura-2-luna-en', 'aura-2-mars-en', 'aura-2-minerva-en',
                    'aura-2-neptune-en', 'aura-2-odysseus-en', 'aura-2-ophelia-en', 'aura-2-orion-en',
                    'aura-2-orpheus-en', 'aura-2-pandora-en', 'aura-2-phoebe-en', 'aura-2-pluto-en',
                    'aura-2-saturn-en', 'aura-2-selene-en', 'aura-2-theia-en', 'aura-2-vesta-en',
                    'aura-2-zeus-en',
                    // Spanish
                    'aura-2-sirio-es', 'aura-2-nestor-es', 'aura-2-carina-es', 'aura-2-celeste-es',
                    'aura-2-alvaro-es', 'aura-2-diana-es', 'aura-2-aquila-es', 'aura-2-selena-es',
                    'aura-2-estrella-es', 'aura-2-javier-es', 'aura-2-agustina-es', 'aura-2-antonia-es',
                    'aura-2-gloria-es', 'aura-2-luciano-es', 'aura-2-olivia-es', 'aura-2-silvia-es',
                    'aura-2-valerio-es',
                    // Dutch
                    'aura-2-beatrix-nl', 'aura-2-daphne-nl', 'aura-2-cornelia-nl', 'aura-2-sander-nl',
                    'aura-2-hestia-nl', 'aura-2-lars-nl', 'aura-2-roman-nl', 'aura-2-rhea-nl', 'aura-2-leda-nl',
                    // French
                    'aura-2-agathe-fr', 'aura-2-hector-fr',
                    // German
                    'aura-2-elara-de', 'aura-2-aurelia-de', 'aura-2-lara-de', 'aura-2-julius-de',
                    'aura-2-fabian-de', 'aura-2-kara-de', 'aura-2-viktoria-de',
                    // Italian
                    'aura-2-melia-it', 'aura-2-elio-it', 'aura-2-flavio-it', 'aura-2-maia-it',
                    'aura-2-cinzia-it', 'aura-2-cesare-it', 'aura-2-livia-it', 'aura-2-perseo-it',
                    'aura-2-dionisio-it', 'aura-2-demetra-it',
                    // Japanese
                    'aura-2-uzume-ja', 'aura-2-ebisu-ja', 'aura-2-fujin-ja', 'aura-2-izanami-ja', 'aura-2-ama-ja',
                ],
                'config' => ['timeout' => 30],
            ]
        );

        $deepgram->models()->updateOrCreate(
            ['name' => 'Aura'],
            [
                'endpoint' => 'v1/speak',
                'voices' => [
                    'aura-asteria-en', 'aura-luna-en', 'aura-stella-en', 'aura-athena-en',
                    'aura-hera-en', 'aura-orion-en', 'aura-arcas-en', 'aura-perseus-en',
                    'aura-angus-en', 'aura-orpheus-en', 'aura-helios-en', 'aura-zeus-en',
                ],
                'config' => ['timeout' => 30],
            ]
        );

        $deepgram->models()->updateOrCreate(
            ['name' => 'Flux'],
            [
                'endpoint' => 'v2/speak',
                'voices' => [
                    'flux-hannah-en', 'flux-kit-en', 'flux-alexis-en', 'flux-cliff-en',
                    'flux-sienna-en', 'flux-cole-en', 'flux-brooke-en', 'flux-colin-en',
                    'flux-gemma-en', 'flux-haley-en', 'flux-heather-en', 'flux-miles-en',
                    'flux-sean-en', 'flux-bree-en', 'flux-brittany-en', 'flux-bruce-en',
                    'flux-conor-en', 'flux-donovan-en', 'flux-drew-en', 'flux-elise-en',
                    'flux-jack-en', 'flux-kai-en', 'flux-kelsey-en', 'flux-maeve-en',
                    'flux-marcelo-en', 'flux-marcus-en', 'flux-meena-en', 'flux-meghan-en',
                    'flux-naveen-en', 'flux-paige-en', 'flux-priya-en', 'flux-rufus-en',
                    'flux-sharon-en', 'flux-tanner-en', 'flux-wade-en', 'flux-wes-en',
                ],
                'config' => ['timeout' => 30],
            ]
        );

        $elevenLabs = VoiceProvider::updateOrCreate(
            ['name' => 'ElevenLabs'],
            [
                'url' => 'https://api.elevenlabs.io/v1/text-to-speech',
                'format' => VoiceProviderFormat::ElevenLabs,
                'api_key' => env('ELEVENLABS_API_KEY'),
                'instructions' => "Requires an ElevenLabs API key. Add ELEVENLABS_API_KEY to your .env file.\n\n"
                    ."The seeded voices below are ElevenLabs' legacy Default voices, which ElevenLabs is retiring on Dec 31, 2026. "
                    .'Grab current voice IDs from your Voice Library (elevenlabs.io/app/voice-library) and add them as models below.',
            ]
        );

        $elevenLabs->models()->updateOrCreate(
            ['endpoint' => 'eleven_multilingual_v2'],
            [
                'name' => 'Multilingual v2',
                'voices' => ['21m00Tcm4TlvDq8ikWAM', 'pNInz6obpgDQGcFmaJgB'],
                'config' => ['timeout' => 30],
            ]
        );

        $elevenLabs->models()->updateOrCreate(
            ['endpoint' => 'eleven_flash_v2_5'],
            [
                'name' => 'Flash v2.5',
                'voices' => ['21m00Tcm4TlvDq8ikWAM', 'pNInz6obpgDQGcFmaJgB'],
                'config' => ['timeout' => 30],
            ]
        );

        $elevenLabs->models()->updateOrCreate(
            ['endpoint' => 'eleven_v3'],
            [
                'name' => 'v3',
                'voices' => ['21m00Tcm4TlvDq8ikWAM', 'pNInz6obpgDQGcFmaJgB'],
                'config' => ['timeout' => 30],
            ]
        );
    }
}
