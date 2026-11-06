<?php

namespace Database\Seeders;

use App\Models\Facility;
use App\Models\Menu;
use App\Models\Page;
use App\Models\Setting;
use App\Models\Url;
use App\Services\PageUrls;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PhysiotherapyContentSeeder extends Seeder
{
    private const MARKER = 'physiotherapy_starter_content_v1';

    public function run(): void
    {
        $marker = Setting::query()->where('key', self::MARKER)->first();
        if ((int) data_get($marker?->value, 'version', 0) >= 3) {
            $this->command?->info('Starter content is already seeded; existing panel content was left unchanged.');

            return;
        }

        DB::transaction(function (): void {
            foreach ($this->settings() as $key => $value) {
                $setting = Setting::query()->firstOrNew(['key' => $key]);

                if (! $setting->exists) {
                    $setting->value = $value;
                    $setting->type = 'text';
                    $setting->save();
                } elseif ($this->isLegacySeedValue($key, $setting->value)) {
                    $setting->value = $value;
                    $setting->save();
                }
            }

            $this->seedFacilities();
            $pages = $this->seedPages();
            $this->seedMenus($pages);

            Setting::query()->updateOrCreate(
                ['key' => self::MARKER],
                ['value' => ['version' => 3], 'type' => 'text'],
            );
        });

        $this->command?->info('Bilingual starter content was seeded without replacing customized settings or existing records.');
    }

    private function settings(): array
    {
        return [
            'site_name' => ['tr' => 'Ozan Çetinyol', 'en' => 'Ozan Çetinyol'],
            'site_description' => [
                'tr' => 'Fizyoterapi değerlendirmesi, kişiye özel hareket planlaması ve randevu süreci hakkında bilgi alın.',
                'en' => 'Learn about physiotherapy assessments, individual movement planning, and appointment requests.',
            ],
            'home_seo_title' => [
                'tr' => 'Fizyoterapi',
                'en' => 'Physiotherapy',
            ],
            'home_seo_description' => [
                'tr' => 'Fizyoterapi değerlendirmesi ve kişiye özel hareket planlaması hakkında bilgi alın. Randevu talebinizi çevrim içi iletin.',
                'en' => 'Learn about physiotherapy assessments and individual movement planning. Send an appointment request online.',
            ],
            'home_slider_1_subtitle' => [
                'tr' => 'Fizyoterapi ve rehabilitasyon',
                'en' => 'Physiotherapy and rehabilitation',
            ],
            'home_slider_1_title' => [
                'tr' => 'Hareket hedeflerinize birlikte odaklanalım',
                'en' => 'Let’s focus on your movement goals together',
            ],
            'home_hero_description' => [
                'tr' => 'İlk değerlendirmeden kişiye özel hareket planlamasına uzanan süreç hakkında bilgi alın.',
                'en' => 'Learn what to expect from an initial assessment and an individual movement plan.',
            ],
            'home_about_subtitle' => [
                'tr' => 'Fizyoterapi yaklaşımı',
                'en' => 'A thoughtful approach to physiotherapy',
            ],
            'home_about_title' => [
                'tr' => 'Size ve hedeflerinize odaklanan bir süreç',
                'en' => 'A process centered on you and your goals',
            ],
            'home_about_content' => [
                'tr' => '<p>Fizyoterapi süreci, kişinin ihtiyaçlarını ve günlük yaşam hedeflerini anlamaya yönelik bir değerlendirmeyle başlar. Bu görüşmede hareket öykünüzü, beklentilerinizi ve varsa önceki değerlendirme bilgilerinizi paylaşabilirsiniz.</p><p>Değerlendirme sonrasında izlenecek yol, kişisel ihtiyaçlarınız ve fizyoterapistinizin klinik değerlendirmesi doğrultusunda planlanır. Süreç boyunca hedefler ve uygulamalar sizinle birlikte gözden geçirilir.</p><p>Web sitesindeki bilgiler genel bilgilendirme amaçlıdır; tanı veya kişiye özel sağlık önerisinin yerine geçmez.</p>',
                'en' => '<p>A physiotherapy journey begins with an assessment designed to understand your needs and everyday goals. You can share your movement history, expectations, and any relevant previous assessment information.</p><p>Next steps are planned according to your individual needs and the physiotherapist’s clinical assessment. Goals and activities are reviewed together as the process continues.</p><p>Information on this website is general and does not replace a diagnosis or individual health advice.</p>',
            ],
            'home_services_subtitle' => [
                'tr' => 'Çalışma alanları',
                'en' => 'Areas of practice',
            ],
            'home_services_title' => [
                'tr' => 'Değerlendirme ve hareket planlaması',
                'en' => 'Assessment and movement planning',
            ],
            'home_testimonials_subtitle' => [
                'tr' => 'Danışan deneyimleri',
                'en' => 'Client experiences',
            ],
            'home_testimonials_title' => [
                'tr' => 'Görüşler',
                'en' => 'Testimonials',
            ],
            'home_news_subtitle' => [
                'tr' => 'Bilgilendirme',
                'en' => 'Information',
            ],
            'home_news_title' => [
                'tr' => 'Hareket ve fizyoterapi hakkında',
                'en' => 'Movement and physiotherapy',
            ],
            'home_location_subtitle' => [
                'tr' => 'İletişim',
                'en' => 'Contact',
            ],
            'home_location_title' => [
                'tr' => 'Bize ulaşın',
                'en' => 'Get in touch',
            ],
            'footer_about_content' => [
                'tr' => 'Fizyoterapi süreci ve randevu talebi hakkında bilgi almak için iletişim kanallarını kullanabilirsiniz.',
                'en' => 'Use the contact details on this page to ask about the physiotherapy process or request an appointment.',
            ],
            'home_sections' => [
                ['section' => 'slider', 'enabled' => true],
                ['section' => 'about', 'enabled' => true],
                ['section' => 'services', 'enabled' => true],
                ['section' => 'testimonials', 'enabled' => true],
                ['section' => 'news', 'enabled' => true],
                ['section' => 'location', 'enabled' => true],
                ['section' => 'appointment', 'enabled' => true],
            ],
        ];
    }

    private function isLegacySeedValue(string $key, mixed $value): bool
    {
        $legacy = [
            'site_description' => [
                'tr' => 'Fizyoterapi ve rehabilitasyon hakkında bilgi alın, randevu talebinizi iletin.',
                'en' => 'Learn about physiotherapy and rehabilitation, and send an appointment request.',
            ],
            'home_seo_title' => [
                'tr' => 'Ozan Çetinyol | Fizyoterapi',
                'en' => 'Ozan Çetinyol | Physiotherapy',
            ],
            'home_seo_description' => [
                'tr' => 'Fizyoterapi ve rehabilitasyon hakkında bilgi alın, randevu talebinizi iletin.',
                'en' => 'Learn about physiotherapy and rehabilitation, and send an appointment request.',
            ],
            'home_slider_1_subtitle' => [
                'tr' => 'Fizyoterapi ve rehabilitasyon',
                'en' => 'Physiotherapy and rehabilitation',
            ],
            'home_slider_1_title' => [
                'tr' => 'Hareket özgürlüğünüz için birlikte çalışalım',
                'en' => 'Let’s work together towards easier movement',
            ],
            'home_about_subtitle' => [
                'tr' => 'Fizyoterapi yaklaşımım',
                'en' => 'My approach to physiotherapy',
            ],
            'home_about_title' => ['tr' => 'Ozan Çetinyol', 'en' => 'Ozan Çetinyol'],
            'home_about_content' => [
                'tr' => '<p>Bu alan fizyoterapistin eğitim, deneyim ve çalışma yaklaşımını tanıtmak için örnek metin olarak bırakılmıştır. Gerçek profil bilgileri daha sonra eklenecektir.</p>',
                'en' => '<p>This placeholder introduces the physiotherapist’s education, experience, and approach. Verified profile details will be added later.</p>',
            ],
            'home_services_subtitle' => ['tr' => 'Hizmetler', 'en' => 'Services'],
            'home_services_title' => ['tr' => 'Çalışma alanları', 'en' => 'Areas of practice'],
            'footer_about_content' => [
                'tr' => 'Fizyoterapi ve rehabilitasyon hizmetleri hakkında bilgi ve randevu için iletişime geçin.',
                'en' => 'Get in touch for information about physiotherapy and rehabilitation services or to request an appointment.',
            ],
        ];

        if (! isset($legacy[$key]) || ! is_array($value)) {
            return false;
        }

        ksort($value);
        ksort($legacy[$key]);

        return $value === $legacy[$key];
    }

    private function seedFacilities(): void
    {
        $facilities = [
            [
                'title' => ['tr' => 'Fizyoterapi değerlendirmesi', 'en' => 'Physiotherapy assessment'],
                'legacy_description' => [
                    'tr' => 'İhtiyaçlarınızı ve hedeflerinizi anlamaya yönelik ilk değerlendirme.',
                    'en' => 'An initial assessment to understand your needs and goals.',
                ],
                'description' => [
                    'tr' => 'Hareket öykünüzü, ihtiyaçlarınızı ve günlük yaşam hedeflerinizi anlamaya yönelik bireysel değerlendirme.',
                    'en' => 'An individual assessment to understand your movement history, needs, and everyday goals.',
                ],
            ],
            [
                'title' => ['tr' => 'Manuel terapi', 'en' => 'Manual therapy'],
                'legacy_description' => [
                    'tr' => 'Kişiye özel değerlendirme doğrultusunda planlanan manuel uygulamalar.',
                    'en' => 'Manual techniques planned according to an individual assessment.',
                ],
                'description' => [
                    'tr' => 'Manuel uygulamaların sizin için uygunluğu ve kapsamı bireysel değerlendirme sonrasında belirlenir.',
                    'en' => 'The suitability and scope of manual techniques are determined after an individual assessment.',
                ],
            ],
            [
                'title' => ['tr' => 'Egzersiz ve rehabilitasyon', 'en' => 'Exercise and rehabilitation'],
                'legacy_description' => [
                    'tr' => 'Hedeflerinize uygun hareket ve egzersiz planlaması.',
                    'en' => 'Movement and exercise planning aligned with your goals.',
                ],
                'description' => [
                    'tr' => 'Hareket ve egzersiz çalışmaları değerlendirme bulguları ve üzerinde anlaşılan hedefler doğrultusunda planlanır.',
                    'en' => 'Movement and exercise activities are planned around assessment findings and agreed goals.',
                ],
            ],
        ];

        foreach ($facilities as $order => $facility) {
            $legacyDescription = $facility['legacy_description'];
            unset($facility['legacy_description']);
            $existing = Facility::withTrashed()
                ->where('title->tr', $facility['title']['tr'])
                ->first();

            if ($existing) {
                if ($existing->description === $legacyDescription) {
                    $existing->update(['description' => $facility['description']]);
                }

                continue;
            }

            Facility::query()->create([
                ...$facility,
                'icon' => 'flaticon-world',
                'order' => $order,
                'is_active' => true,
            ]);
        }
    }

    private function seedPages(): array
    {
        $pages = [
            [
                'slug' => 'ilk-gorusme',
                'title' => ['tr' => 'İlk görüşme nasıl ilerler?', 'en' => 'What to expect at your first appointment'],
                'seo_description' => [
                    'tr' => 'Fizyoterapi sürecindeki ilk görüşme ve değerlendirme hakkında genel bilgi.',
                    'en' => 'General information about the first appointment and physiotherapy assessment.',
                ],
                'description' => [
                    'tr' => '<h2>İlk görüşmenin amacı</h2><p>İlk görüşmede ihtiyaçlarınız, hareket öykünüz ve günlük yaşam hedefleriniz hakkında konuşulur. Değerlendirme, sizin paylaştığınız bilgiler ve fizyoterapistin klinik gözlemi doğrultusunda şekillenir.</p><h2>Görüşmeye hazırlanırken</h2><p>Randevu talebinizde size ulaşılabilecek güncel bir telefon numarası paylaşın. İsterseniz görüşme sırasında sormak istediğiniz soruları önceden not edebilirsiniz. Sağlık belgelerinizi paylaşmanız gerekip gerekmediğini randevu öncesinde kliniğe danışın.</p><h2>Sonraki adımlar</h2><p>Değerlendirme sonrasında olası çalışma hedefleri ve takip süreci sizinle konuşulur. Uygulamalar kişiye göre değişir; sonuç veya iyileşme süresi konusunda önceden garanti verilemez.</p><p>Bu sayfa genel bilgilendirme amaçlıdır ve tıbbi değerlendirme yerine geçmez.</p>',
                    'en' => '<h2>The purpose of the first appointment</h2><p>The first appointment is a chance to discuss your needs, movement history, and everyday goals. The assessment is guided by the information you share and the physiotherapist’s clinical observations.</p><h2>Preparing for your visit</h2><p>Please provide a current phone number with your appointment request. You may wish to note down questions in advance. Contact the clinic before your appointment if you are unsure whether to bring health records.</p><h2>Next steps</h2><p>Possible goals and follow-up are discussed after the assessment. Activities vary from person to person; outcomes or recovery times cannot be guaranteed in advance.</p><p>This page provides general information and is not a substitute for medical assessment.</p>',
                ],
            ],
            [
                'slug' => 'sik-sorulan-sorular',
                'title' => ['tr' => 'Sık sorulan sorular', 'en' => 'Frequently asked questions'],
                'seo_description' => [
                    'tr' => 'Randevu talebi ve fizyoterapi değerlendirmesi hakkında sık sorulan sorular.',
                    'en' => 'Frequently asked questions about appointment requests and physiotherapy assessments.',
                ],
                'description' => [
                    'tr' => '<h2>Nasıl randevu talep edebilirim?</h2><p>Web sitesindeki randevu formunu doldurarak talebinizi iletebilirsiniz. Talep, randevunun kesinleştiği anlamına gelmez; uygunluk için sizinle iletişime geçilir.</p><h2>İlk değerlendirmede neler konuşulur?</h2><p>İhtiyaçlarınız, hareket öykünüz ve günlük yaşam hedefleriniz ele alınır. Değerlendirme ve sonraki adımlar kişiye göre belirlenir.</p><h2>Egzersiz planı herkes için aynı mıdır?</h2><p>Hayır. Hareket ve egzersiz çalışmaları kişinin ihtiyaçları ve değerlendirme bulguları doğrultusunda planlanır; genel internet bilgileri kişisel öneri yerine geçmez.</p><h2>Bu bilgiler tıbbi tavsiye midir?</h2><p>Hayır. Bu sayfadaki bilgiler genel bilgilendirme amaçlıdır. Sağlık şikâyetleriniz için nitelikli bir sağlık profesyonelinden kişisel değerlendirme alın.</p>',
                    'en' => '<h2>How can I request an appointment?</h2><p>Send a request using the appointment form on this website. A request does not confirm an appointment; you will be contacted to discuss availability.</p><h2>What happens during the initial assessment?</h2><p>Your needs, movement history, and everyday goals are discussed. The assessment and next steps are individual.</p><h2>Is the exercise plan the same for everyone?</h2><p>No. Movement and exercise activities are planned according to individual needs and assessment findings; general online information is not personal advice.</p><h2>Is this medical advice?</h2><p>No. This page provides general information only. For health concerns, seek an individual assessment from a qualified health professional.</p>',
                ],
            ],
        ];

        $created = [];
        foreach ($pages as $data) {
            $url = Url::withTrashed()->where('slug', $data['slug'])->first();
            if ($url) {
                if (Page::query()->whereKey($url->page_id)->exists()) {
                    $created[$data['slug']] = $url->page_id;
                }

                continue;
            }

            $slug = $data['slug'];
            unset($data['slug']);
            $page = Page::query()->create($data);
            PageUrls::save($page, $slug);
            $created[$slug] = $page->id;
        }

        return $created;
    }

    private function seedMenus(array $pages): void
    {
        if (! Menu::query()->where('type', 'header')->exists()) {
            $header = [
                ['title' => ['tr' => 'Ana sayfa', 'en' => 'Home'], 'custom_url' => '#slider'],
                ['title' => ['tr' => 'Hakkımda', 'en' => 'About'], 'custom_url' => '#about'],
                ['title' => ['tr' => 'Çalışma alanları', 'en' => 'Areas of practice'], 'custom_url' => '#services'],
                ['title' => ['tr' => 'İlk görüşme', 'en' => 'Your first appointment'], 'page_id' => $pages['ilk-gorusme'] ?? null],
                ['title' => ['tr' => 'Sık sorulan sorular', 'en' => 'FAQs'], 'page_id' => $pages['sik-sorulan-sorular'] ?? null],
                ['title' => ['tr' => 'İletişim', 'en' => 'Contact'], 'custom_url' => '#contact'],
            ];

            foreach ($header as $order => $menu) {
                Menu::query()->create(['type' => 'header', 'order' => $order, ...$menu]);
            }
        }

        if (! Menu::query()->where('type', 'footer')->exists()) {
            $footer = [
                ['title' => ['tr' => 'İlk görüşme', 'en' => 'Your first appointment'], 'page_id' => $pages['ilk-gorusme'] ?? null],
                ['title' => ['tr' => 'Sık sorulan sorular', 'en' => 'FAQs'], 'page_id' => $pages['sik-sorulan-sorular'] ?? null],
                ['title' => ['tr' => 'Randevu talebi', 'en' => 'Request an appointment'], 'custom_url' => '#appointment'],
            ];

            foreach ($footer as $order => $menu) {
                Menu::query()->create(['type' => 'footer', 'order' => $order, ...$menu]);
            }
        }
    }
}
