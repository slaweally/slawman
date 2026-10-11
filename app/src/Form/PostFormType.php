<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Category;
use App\Entity\Post;
use App\Enum\PostStatus;
use App\Enum\PostType;
use App\PostType\PostTypeConfig;
use App\Service\PostMetaService;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;

class PostFormType extends AbstractType
{
    public function __construct(private readonly PostMetaService $postMetaService)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'required' => false,
                'label' => 'Başlık',
                'attr' => ['class' => 'form-control', 'data-field' => 'title'],
            ])
            ->add('slug', TextType::class, [
                'required' => false,
                'label' => 'URL Slug',
                'attr' => ['class' => 'form-control', 'placeholder' => 'otomatik oluşturulur'],
            ])
            ->add('excerpt', TextareaType::class, [
                'required' => false,
                'label' => 'Özet',
                'constraints' => [
                    new Length(max: Post::EXCERPT_MAX_LENGTH, maxMessage: 'Özet en fazla {{ limit }} karakter olabilir.'),
                ],
                'attr' => [
                    'rows' => 3,
                    'class' => 'form-control',
                    'data-field' => 'excerpt',
                    'maxlength' => (string) Post::EXCERPT_MAX_LENGTH,
                    'placeholder' => 'Kısa özet (en fazla 500 karakter)',
                ],
            ])
            ->add('content', TextareaType::class, [
                'label' => 'İçerik',
                'attr' => [
                    'rows' => 20,
                    'id' => 'post-content-editor',
                    'class' => 'form-control post-content-field',
                    'data-field' => 'content',
                ],
            ])
            ->add('type', EnumType::class, [
                'class' => PostType::class,
                'label' => 'İçerik Türü',
                'choice_label' => fn (PostType $t) => PostTypeConfig::label($t),
                'attr' => ['id' => 'post-type-select', 'class' => 'form-select'],
            ])
            ->add('status', EnumType::class, [
                'class' => PostStatus::class,
                'label' => 'Durum',
                'choices' => [PostStatus::Published, PostStatus::Scheduled, PostStatus::Draft],
                'choice_label' => fn (PostStatus $s) => match ($s) {
                    PostStatus::Published => 'Yayınla',
                    PostStatus::Scheduled => 'Zamanla',
                    PostStatus::Draft => 'Taslak',
                    default => $s->value,
                },
                'data' => $options['data'] instanceof Post && $options['data']->getId() !== null
                    ? $options['data']->getStatus()
                    : PostStatus::Published,
                'attr' => ['class' => 'form-select form-select-sm', 'id' => 'post-status-select'],
            ])
            ->add('publishedAt', DateTimeType::class, [
                'label' => 'Yayın Tarihi',
                'required' => false,
                'widget' => 'single_text',
                'html5' => true,
                'input' => 'datetime',
                // Entity tarafında new \DateTime() sunucunun varsayılan (php.ini date.timezone)
                // dilimini kullanıyor — form burada sabit bir dilim (örn. Europe/Istanbul)
                // dayatırsa sunucu UTC ise "timezone mismatch" LogicException'ı fırlatılır.
                // Sunucunun kendi varsayılanını kullanmak entity ile formu her zaman senkron tutar.
                'model_timezone' => date_default_timezone_get(),
                'view_timezone' => date_default_timezone_get(),
                'help' => 'Tarih şu andan ileriyse durum otomatik "Zamanla" olur; şu an veya geçmişse otomatik "Yayınla" olur. "Taslak" seçiliyken tarih dikkate alınmaz.',
                'attr' => [
                    'class' => 'form-control',
                    'id' => 'post-published-at',
                ],
            ])
            ->add('programmingLanguage', TextType::class, [
                'required' => false,
                'label' => 'Programlama Dili',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'php, javascript, python…',
                    'data-field' => 'programmingLanguage',
                ],
            ])
            ->add('featuredImage', HiddenType::class, [
                'required' => false,
                'attr' => ['id' => 'featured-image-input'],
            ])
            ->add('isFeatured', CheckboxType::class, [
                'required' => false,
                'label' => 'Öne çıkan',
                'attr' => ['class' => 'form-check-input'],
            ])
            ->add('isSticky', CheckboxType::class, [
                'required' => false,
                'label' => 'Sabit',
                'attr' => ['class' => 'form-check-input'],
            ])
            ->add('categories', EntityType::class, [
                'class' => Category::class,
                'choice_label' => 'name',
                'multiple' => true,
                'required' => false,
                'label' => 'Kategoriler',
                'attr' => ['class' => 'form-select form-select-sm', 'size' => 4],
            ])
            ->add('seoTitle', TextType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Meta Başlık',
                'attr' => ['class' => 'form-control', 'maxlength' => 70],
            ])
            ->add('seoDescription', TextareaType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Meta Açıklama',
                'attr' => ['rows' => 3, 'class' => 'form-control', 'maxlength' => 160],
            ])
            ->add('seoKeywords', TextType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Anahtar Kelimeler',
                'attr' => ['class' => 'form-control', 'placeholder' => 'virgülle ayırın'],
            ])
            ->add('ogImage', HiddenType::class, [
                'mapped' => false,
                'required' => false,
                'attr' => ['id' => 'og-image-input'],
            ])
            ->add('repoUrl', TextType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Depo URL (GitHub vb.)',
                'attr' => ['class' => 'form-control', 'placeholder' => 'https://github.com/...'],
            ])
            ->add('demoUrl', TextType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Canlı Demo URL',
                'attr' => ['class' => 'form-control', 'placeholder' => 'https://...'],
            ])
            ->add('techStack', TextareaType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Teknoloji Yığını',
                'attr' => [
                    'rows' => 3,
                    'class' => 'form-control',
                    'placeholder' => 'Symfony, Vue, Docker (virgülle veya satır satır)',
                ],
            ])
            ->add('gallery', TextareaType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Galeri Görselleri',
                'attr' => [
                    'rows' => 4,
                    'id' => 'post-gallery-field',
                    'class' => 'form-control font-monospace',
                    'placeholder' => 'Her satıra bir görsel URL',
                ],
            ])
            ->add('version', TextType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Sürüm',
                'attr' => ['class' => 'form-control', 'placeholder' => '1.0.0'],
            ])
            ->add('distribution', ChoiceType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Dağıtım Türü',
                'choices' => PostTypeConfig::distributionChoices(),
                'placeholder' => 'Seçin…',
                'attr' => ['class' => 'form-select'],
            ])
            ->add('downloadUrl', TextType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'İndirme URL (isteğe bağlı)',
                'attr' => [
                    'class' => 'form-control',
                    'id' => 'software-download-url',
                    'placeholder' => 'https://… veya dosya yükleyin',
                ],
            ])
            ->add('downloadFile', FileType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'İndirme Dosyası',
                'attr' => [
                    'class' => 'form-control',
                    'id' => 'software-download-file',
                    'accept' => '.zip,.7z,.tar,.gz,.tgz,.apk,.exe,.msi,.dmg,.deb,.rpm,.jar,.pdf',
                ],
            ])
            ->add('sourceUrl', TextType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Kaynak Kodu URL',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('changelog', TextareaType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Değişiklik Günlüğü',
                'attr' => ['rows' => 8, 'class' => 'form-control font-monospace'],
            ])
            ->add('license', TextType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Lisans',
                'attr' => ['class' => 'form-control', 'placeholder' => 'MIT, GPL-3.0…'],
            ])
            ->add('difficulty', ChoiceType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Zorluk Seviyesi',
                'choices' => [
                    'Kolay' => 'easy',
                    'Orta' => 'medium',
                    'Zor' => 'hard',
                ],
                'placeholder' => 'Seçin…',
                'attr' => ['class' => 'form-select'],
            ])
            ->add('estimatedTime', TextType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Tahmini Süre',
                'attr' => ['class' => 'form-control', 'placeholder' => 'örn. 30 dakika, 2 saat'],
            ])
            ->add('prerequisites', TextareaType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Ön Koşullar',
                'attr' => [
                    'rows' => 4,
                    'class' => 'form-control',
                    'placeholder' => 'Her satıra bir ön koşul (Linux temel bilgisi, root erişimi vb.)',
                ],
            ])
            ->add('toolsRequired', TextareaType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Gerekli Araçlar',
                'attr' => [
                    'rows' => 3,
                    'class' => 'form-control',
                    'placeholder' => 'USB bellek, VirtualBox, terminal erişimi…',
                ],
            ])
            ->add('errorContent', TextareaType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Hata Mesajı / Kodu',
                'attr' => [
                    'rows' => 8,
                    'class' => 'form-control font-monospace',
                    'placeholder' => 'Kullanıcının gördüğü hata mesajı, log çıktısı veya ekran görüntüsü açıklaması',
                ],
            ])
            ->add('errorContext', TextareaType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Hata Bağlamı',
                'attr' => [
                    'rows' => 3,
                    'class' => 'form-control',
                    'placeholder' => 'Ne zaman oluşuyor? (Ubuntu 22.04, PHP 8.3, composer install sırasında…)',
                ],
            ])
            ->add('snippetNotes', TextareaType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Kullanım Notları',
                'attr' => [
                    'rows' => 8,
                    'id' => 'snippet-notes-editor',
                    'class' => 'form-control',
                    'placeholder' => 'Kodun nasıl kullanılacağı, püf noktalar, dikkat edilecekler…',
                ],
            ])
            ->add('compareSideA', TextType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Taraf A Adı',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Apache'],
            ])
            ->add('compareSideB', TextType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Taraf B Adı',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Nginx'],
            ])
            ->add('compareSideASub', TextType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Taraf A Alt Başlık',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Klasik web sunucusu'],
            ])
            ->add('compareSideBSub', TextType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Taraf B Alt Başlık',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Yüksek performanslı reverse proxy'],
            ])
            ->add('compareWinner', ChoiceType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Genel Kazanan',
                'choices' => [
                    'Belirsiz' => 'none',
                    'Taraf A' => 'a',
                    'Taraf B' => 'b',
                    'Berabere' => 'tie',
                ],
                'attr' => ['class' => 'form-select'],
            ])
            ->add('compareVerdict', TextareaType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Sonuç / Özet',
                'attr' => [
                    'rows' => 3,
                    'class' => 'form-control',
                    'placeholder' => 'Kısa karar özeti — hangi senaryoda hangisi tercih edilmeli?',
                ],
            ])
            ->add('compareCriteria', TextareaType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Karşılaştırma Tablosu',
                'attr' => [
                    'rows' => 8,
                    'class' => 'form-control font-monospace',
                    'placeholder' => "Her satır: Kriter | Taraf A | Taraf B | kazanan (a/b/tie/-)\nPerformans | İyi | Mükemmel | b\nBellek | Düşük | Orta | a",
                ],
            ]);

        /**
         * Akıllı durum/tarih otomasyonu — kullanıcının seçtiği durum ne olursa olsun
         * gönderilen ham veriyi (henüz entity'ye map edilmeden) düzeltir:
         * - "draft" seçiliyse tarih ne olursa olsun durum draft kalır.
         * - "published"/"scheduled" seçiliyse: publishedAt şu andan ileriyse "scheduled",
         *   şu an veya geçmişteyse "published" olarak zorlanır.
         * Böylece formu atlayan/manipüle eden bir istek de her zaman tutarlı kalır.
         */
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event): void {
            $data = $event->getData();
            if (!is_array($data)) {
                return;
            }

            $status = $data['status'] ?? null;
            if ($status === PostStatus::Draft->value) {
                return;
            }

            $rawDate = trim((string) ($data['publishedAt'] ?? ''));
            $publishedAt = $rawDate !== '' ? \DateTime::createFromFormat('Y-m-d\TH:i', $rawDate) : null;
            $now = new \DateTime();

            $data['status'] = ($publishedAt instanceof \DateTime && $publishedAt > $now)
                ? PostStatus::Scheduled->value
                : PostStatus::Published->value;

            $event->setData($data);
        });

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event): void {
            $post = $event->getData();
            if (!$post instanceof Post || $post->getId() === null) {
                return;
            }

            $form = $event->getForm();
            $seo = $this->postMetaService->getSeoData($post);
            $typeMeta = $this->postMetaService->getTypeMeta($post);

            $form->get('seoTitle')->setData($seo[PostTypeConfig::META_SEO_TITLE] ?? '');
            $form->get('seoDescription')->setData($seo[PostTypeConfig::META_SEO_DESCRIPTION] ?? '');
            $form->get('seoKeywords')->setData($seo[PostTypeConfig::META_SEO_KEYWORDS] ?? '');
            $form->get('ogImage')->setData($seo[PostTypeConfig::META_OG_IMAGE] ?? '');

            $form->get('repoUrl')->setData($typeMeta[PostTypeConfig::META_REPO_URL] ?? '');
            $form->get('demoUrl')->setData($typeMeta[PostTypeConfig::META_DEMO_URL] ?? '');
            $form->get('techStack')->setData($typeMeta[PostTypeConfig::META_TECH_STACK] ?? '');
            $form->get('gallery')->setData($typeMeta[PostTypeConfig::META_GALLERY] ?? '');
            $form->get('version')->setData($typeMeta[PostTypeConfig::META_VERSION] ?? '');
            $form->get('distribution')->setData($typeMeta[PostTypeConfig::META_DISTRIBUTION] ?? '');
            $form->get('downloadUrl')->setData($typeMeta[PostTypeConfig::META_DOWNLOAD_URL] ?? '');
            $form->get('sourceUrl')->setData($typeMeta[PostTypeConfig::META_SOURCE_URL] ?? '');
            $form->get('changelog')->setData($typeMeta[PostTypeConfig::META_CHANGELOG] ?? '');
            $form->get('license')->setData($typeMeta[PostTypeConfig::META_LICENSE] ?? '');

            $form->get('difficulty')->setData($typeMeta[PostTypeConfig::META_DIFFICULTY] ?? '');
            $form->get('estimatedTime')->setData($typeMeta[PostTypeConfig::META_ESTIMATED_TIME] ?? '');
            $form->get('prerequisites')->setData($typeMeta[PostTypeConfig::META_PREREQUISITES] ?? '');
            $form->get('toolsRequired')->setData($typeMeta[PostTypeConfig::META_TOOLS_REQUIRED] ?? '');

            $form->get('errorContent')->setData($typeMeta[PostTypeConfig::META_ERROR_CONTENT] ?? '');
            $form->get('errorContext')->setData($typeMeta[PostTypeConfig::META_ERROR_CONTEXT] ?? '');

            $form->get('snippetNotes')->setData($typeMeta[PostTypeConfig::META_SNIPPET_NOTES] ?? '');

            $form->get('compareSideA')->setData($typeMeta[PostTypeConfig::META_COMPARE_SIDE_A] ?? '');
            $form->get('compareSideB')->setData($typeMeta[PostTypeConfig::META_COMPARE_SIDE_B] ?? '');
            $form->get('compareSideASub')->setData($typeMeta[PostTypeConfig::META_COMPARE_SIDE_A_SUB] ?? '');
            $form->get('compareSideBSub')->setData($typeMeta[PostTypeConfig::META_COMPARE_SIDE_B_SUB] ?? '');
            $form->get('compareWinner')->setData($typeMeta[PostTypeConfig::META_COMPARE_WINNER] ?? 'none');
            $form->get('compareVerdict')->setData($typeMeta[PostTypeConfig::META_COMPARE_VERDICT] ?? '');
            $form->get('compareCriteria')->setData($typeMeta[PostTypeConfig::META_COMPARE_CRITERIA] ?? '');
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Post::class]);
    }
}
