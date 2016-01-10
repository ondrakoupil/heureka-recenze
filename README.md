# PHP knihovna pro zpracování recenzí z Heuréky

## Instalace

Nainstalujte knihovnu přes Composer:

```
composer install ondrakoupil\heureka
```

Nepoužíváte-li Composer, includujte soubor `dist/heureka.php`, který obsahuje celou knihovnu pohromadě, 
anebo libovolným způsobem zajistěte autoloading tříd z adresáře `src`.

## Použití

Knihovna za vás zajistí stažení a parsování XML souborů s exporty recenzí z Heuréky a v případě
recenzí produktů je umí sdružit dle jednotlivých produktů a případně pro ně vytvořit souhrnné hodnocení.
Co naopak neřeší je ukládání načtených recenzí a integraci s vaším webem / eshopem / aplikací - tuto část si musíte doprogramovat sami.

Jak jistě víte, Heuréka sbírá od vašich zákazníků dva typy recenzí - jednak názory na váš e-shop jako takový,
kde se zákazníci mohou vyjádřit k spolehlivosti, komunikaci, dodacím lhůtám, přehlednosti webu apod., 
a jednak ke konkrétním produktům, které koupili. Heuréka bohužel poskytne jen recenze k objednávkám z vašeho e-shopu,
i když daný produkt nakoupilo spousta lidí na jiných e-shopech.

Recenzím e-shopu se věnují třídy `EshopReviewsClient` a `EshopReview`, 
recenzím produktů se věnují `ProductReviewsClient`, `ProductReview` a `ProductReviewSummary`.

Kromě knihovny budete potřebovat ještě tajný klíč, který váš e-shop používá pro komunikaci s Heurékou. Klíč kdyžtak
získáte v administraci Heuréky ve svém účtu v sekci [Ověřeno zákazníky][overeno], podrobnější popis exportu recenzí 
najdete [zde][xml-spec].

Následující příklady neuvádějí celá jména tříd - buď si před ně doplňte namespace `OndraKoupil\Heureka`, anebo na začátek programu doplňte

```
use \OndraKoupil\Heureka\EshopReviewsClient, \OndraKoupil\Heureka\EshopReview,
    \OndraKoupil\Heureka\ProductReviewsClient, \OndraKoupil\Heureka\ProductReview, \OndraKoupil\Heureka\ProductReviewSummary;
```


### Vytvoření klienta

Při instancování třídy klienta lze zadat rovnou tajný klíč, anebo celou adresu (vhodné pro SK Heuréku). U recenzí
produktů lze navíc omezit, jak staré recenze chcete (max. 6 měsíců). U recenzí e-shopu tento parametr Heuréka nepodporuje.

```
$client  = new   EshopReviewsClient("my-secret-key");
$client2 = new   EshopReviewsClient("http://www.heureka.sk/direct/dotaznik/export-review.php?key=my-secret-key");
$client3 = new ProductReviewsClient("my-secret-key");
$client4 = new ProductReviewsClient("http://www.heureka.sk/direct/dotaznik/export-product-review.php?key=my-secret-key");
$client5 = new ProductReviewsClient("my-secret-key", new DateTime("now - 1 month") );

$client6 = new EshopReviewsClient();
$client6->setSourceAddress("http://www.heureka.cz/direct/dotaznik/export-review.php?key=my-secret-key");
```

### Nastavení

Oba klienti interně využívají XmlReader, stažené XML zpracovávají sekvenčně a jsou tedy schopni zprocesovat
i velké XML soubory s relativně malými nároky na paměť. K tomu ale potřebují možnost stáhnout si nejprve 
XML soubor z Heuréky někam do dočasného umístění a odtamtud si je postupně číst.
Druhý argument umožňuje nastavit, zda po úspěšném zpracování má být dočasný soubor automaticky smazán (default true).

```
$client->setTempFile("tempfile.xml");
```

Dále je třeba implementovat nějakou funkci, která bude načtené recenze zpracovávat. Funkce dostane jako
argument objekt třídy `EshopReview` nebo `ProductReview` a s ní si může dělat, co chce. 
Nejspíš recenzi uloží někam do databáze. Obě třídy jsou obyčejné hloupé třídy s public proměnnými
(více v jejich [dokumentaci][doc]) a s metodou `getAsArray()`, která je kdyžtak převede do podoby obyčejného pole.

Určená funkce se zavolá jednou pro každou recenzi, která je z Heuréky stažena.

```
$client->setCallback( 
	function(EshopReview $review) {
		// ... zde si udělejte, co hrdlo ráčí
		print_r($review->getAsArray());
	}
);
```

### Stažení a zpracování souboru

A pak už jen stačí zavolat metodu `run()`. Klient stáhne soubor a jeho obsah recenzi po recenzi postupně 
předá dříve definované funkci.

```
$client->run();
```


Pokud byste z nějakého důvodu nechtěli soubor rovnou zpracovávat, lze ho pouze stáhnout:

```
$client->download("heureka-recenze.xml");
```


Anebo naopak, pokud již máte soubor stažený, lze klientovi říct, že ho nemá stahovat
a místo toho použít zadaný soubor:

```
$client->useFile("path/to/downloaded/file.xml");
$client->run();
```


### Recenze produktů

U recenzí produktů může být užitečné je sdružovat podle jednotlivých produktů, kterých se týkají, případně
vás může zajímat jen souhrnné hodnocení a ne jednotlivé recenze. Heuréka bohužel vyexportuje nesetříděnou hromadu recenzí 
a o jejich roztřídění se musíte postarat sami. Knihovna vám s tím pomůže a data z XML souboru přelouská
do podoby přehledných výsledků v objektech třídy `ProductReviewSummary`.

První zádrhel bude, že v exportu z Heuréky nenajdete žádný jednoznačný identifikátor produktu (alespoň ne nyní), 
i když ve vašem feedu máte uvedeno <ITEM_ID>. To celou věc trochu komplikuje. Nejprve je tedy třeba implementovat nějakou funkci,
která převádí dostupná data na jednoznačné ID ve vašem e-shopu. ID může být číslo nebo řetězec, zkrátka libovolná skalární hodnota.
V tomto příkladu prostě předpokládám, že každý produkt má právě jednu URL, která je vždy jedinečná a stejná.
Pro stejné produkty se definovaná funkce spustí jen jednou.

Pokud funkci nenastavíte, nic se neděje, jen nebude možné používat proměnnou `$productId` v objektech ProductReview a žádné z níže uvedených funkcí.

```
$client->setIdResolver(
	function(ProductReview $review) {
		return $review->productUrl; 
	}
);
```

Dále si můžete nastavit, zda chcete nebo nechcete zpracovávat souhrnné informace o produktech.
Pokud to neuděláte, funkce pracující s ProductReviewSummary budou vracet null nebo prázdné pole.

```
$client->setSaveSummary(true);
$client2->setSaveSummary(true, true);
```

Druhý argument říká, zda chcete ukládat pro pozdější použítí úplně všechny recenze (u větších feedů
to může být dost náročné na paměť). Pokud ho dáte false (nebo vynecháte úplně), tak budou mít poskytované 
objekty ProductReviewSummary v proměnné `$reviews` vždy jen prázdné pole. Recenze si můžete tak jako tak
postupně poukládat ve funkci definované přes `setCallback()`.

Poté lze klasicky spustit `run()` a využít různé metody vracející souhrnná data.

```
$client->run();

// Všechna nalezená ID produktů jako array
$client->getAllProductIds(); 

// Všechny souhrnné informace jako array [ID_produktu] => ProductReviewSummary
$client->getAllSummaries();

// Souhrn recenzí o produktu s konkrétním ID jako objekt ProductReviewSummary
$summary = $client->getSummaryOfProduct(12345);
echo "Celkem $summary->reviewCount recenzí, hodnocení $summary->averageRating z 5";

// Všechny recenze týkající se konkrétního produktu
$reviews = $client->getReviewsOfProduct(12345);
```

Samozřejmě, celou taškařici okolo ProductReviewSummary můžete ignorovat a jen načtené recenze dál zpracovat
pomocí funkce definované v `setCallback()`.


## Problémy?

Pokud jste narazili na bug, něco nefunguje nebo máte návrh na zlepšení, přidejte issue nebo mě bez obav [kontaktujte napřímo][ondrasek] :-)



[overeno]: http://sluzby.heureka.cz/sluzby/certifikat-spokojenosti/
[doc]: docs/index.html
[xml-spec]: http://sluzby.heureka.cz/napoveda/widget-a-ikonky-ze-sluzby-overeno-zakazniky/
[ondrasek]: mailto:ondrej.koupil@optimato.cz
