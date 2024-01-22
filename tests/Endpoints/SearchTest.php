<?php

declare(strict_types=1);

namespace Tests\Endpoints;

use Meilisearch\Endpoints\Indexes;
use Meilisearch\Exceptions\ApiException;
use Meilisearch\Http\Client;
use Tests\TestCase;

final class SearchTest extends TestCase
{
    private Indexes $index;

    protected function setUp(): void
    {
        parent::setUp();
        $this->index = $this->createEmptyIndex($this->safeIndexName());
        $promise = $this->index->updateDocuments(self::DOCUMENTS);
        $this->index->waitForTask($promise['taskUid']);
    }

    public function testBasicSearch(): void
    {
        $response = $this->index->search('prince');

        $this->assertEstimatedPagination($response->toArray());
        self::assertSame(2, $response->getEstimatedTotalHits());
        self::assertCount(2, $response->getHits());

        $response = $this->index->search('prince', [], [
            'raw' => true,
        ]);

        $this->assertEstimatedPagination($response);
        self::assertSame(2, $response['estimatedTotalHits']);
    }

    public function testBasicSearchWithFinitePagination(): void
    {
        $response = $this->index->search('prince', ['hitsPerPage' => 2]);

        $this->assertFinitePagination($response->toArray());
        self::assertCount(2, $response->getHits());

        $response = $this->index->search('prince', ['hitsPerPage' => 2], [
            'raw' => true,
        ]);

        $this->assertFinitePagination($response);
    }

    public function testBasicEmptySearch(): void
    {
        $response = $this->index->search('');

        $this->assertEstimatedPagination($response->toArray());
        self::assertCount(7, $response->getHits());

        $response = $this->index->search('', [], [
            'raw' => true,
        ]);

        $this->assertEstimatedPagination($response);
        self::assertSame(7, $response['estimatedTotalHits']);
    }

    public function testBasicPlaceholderSearch(): void
    {
        $response = $this->index->search(null);

        $this->assertEstimatedPagination($response->toArray());
        self::assertCount(\count(self::DOCUMENTS), $response->getHits());

        $response = $this->index->search(null, [], [
            'raw' => true,
        ]);

        $this->assertEstimatedPagination($response);
        self::assertSame(\count(self::DOCUMENTS), $response['estimatedTotalHits']);
    }

    public function testSearchWithOptions(): void
    {
        $response = $this->index->search('prince', ['limit' => 1]);

        self::assertCount(1, $response->getHits());

        $response = $this->index->search('prince', ['limit' => 1], [
            'raw' => true,
        ]);

        self::assertCount(1, $response['hits']);
    }

    public function testBasicSearchIfNoPrimaryKeyAndDocumentProvided(): void
    {
        $emptyIndex = $this->createEmptyIndex($this->safeIndexName('empty'));

        $res = $emptyIndex->search('prince');

        $this->assertEstimatedPagination($res->toArray());
        self::assertCount(0, $res->getHits());

        $res = $emptyIndex->search('prince', [], [
            'raw' => true,
        ]);

        $this->assertEstimatedPagination($res);
        self::assertSame(0, $res['estimatedTotalHits']);
    }

    public function testExceptionIfNoIndexWhenSearching(): void
    {
        $index = $this->createEmptyIndex($this->safeIndexName('movie-1'));
        $res = $index->delete();
        $index->waitForTask($res['taskUid']);

        $this->expectException(ApiException::class);

        $index->search('prince');
    }

    public function testParametersCropMarker(): void
    {
        $response = $this->index->search('blood', [
            'limit' => 1,
            'attributesToCrop' => ['title'],
            'cropLength' => 2,
        ]);

        self::assertArrayHasKey('_formatted', $response->getHit(0));
        self::assertSame('…Half-Blood…', $response->getHit(0)['_formatted']['title']);

        $response = $this->index->search('blood', [
            'limit' => 1,
            'attributesToCrop' => ['title'],
            'cropLength' => 2,
        ], [
            'raw' => true,
        ]);

        self::assertArrayHasKey('_formatted', $response['hits'][0]);
        self::assertSame('…Half-Blood…', $response['hits'][0]['_formatted']['title']);
    }

    public function testParametersWithCustomizedCropMarker(): void
    {
        $response = $this->index->search('blood', [
            'limit' => 1,
            'attributesToCrop' => ['title'],
            'cropLength' => 3,
            'cropMarker' => '(ꈍᴗꈍ)',
        ]);

        self::assertArrayHasKey('_formatted', $response->getHit(0));
        self::assertSame('(ꈍᴗꈍ)Half-Blood Prince', $response->getHit(0)['_formatted']['title']);

        $response = $this->index->search('blood', [
            'limit' => 1,
            'attributesToCrop' => ['title'],
            'cropLength' => 3,
            'cropMarker' => '(ꈍᴗꈍ)',
        ], [
            'raw' => true,
        ]);

        self::assertArrayHasKey('_formatted', $response['hits'][0]);
        self::assertSame('(ꈍᴗꈍ)Half-Blood Prince', $response['hits'][0]['_formatted']['title']);
    }

    public function testSearchWithMatchingStrategyALL(): void
    {
        $response = $this->index->updateSearchableAttributes(['comment']);
        $this->index->waitForTask($response['taskUid']);

        $response = $this->index->search('another french book', [
            'matchingStrategy' => 'all',
        ]);

        self::assertCount(1, $response->getHits());
    }

    public function testSearchWithMatchingStrategyLAST(): void
    {
        $response = $this->index->updateSearchableAttributes(['comment']);
        $this->index->waitForTask($response['taskUid']);

        $response = $this->index->search('french book', [
            'matchingStrategy' => 'last',
        ]);

        self::assertCount(2, $response->getHits());
    }

    public function testParametersWithHighlightTag(): void
    {
        $response = $this->index->search('and', [
            'limit' => 1,
            'attributesToHighlight' => ['*'],
        ]);

        self::assertArrayHasKey('_formatted', $response->getHit(0));
        self::assertSame('Pride <em>and</em> Prejudice', $response->getHit(0)['_formatted']['title']);

        $response = $this->index->search('and', [
            'limit' => 1,
            'attributesToHighlight' => ['*'],
        ], [
            'raw' => true,
        ]);

        self::assertArrayHasKey('_formatted', $response['hits'][0]);
        self::assertSame('Pride <em>and</em> Prejudice', $response['hits'][0]['_formatted']['title']);
    }

    public function testParametersWithCustomizedHighlightTag(): void
    {
        $response = $this->index->search('and', [
            'limit' => 1,
            'attributesToHighlight' => ['*'],
            'highlightPreTag' => '(⊃｡•́‿•̀｡)⊃ ',
            'highlightPostTag' => ' ⊂(´• ω •`⊂)',
        ]);

        self::assertArrayHasKey('_formatted', $response->getHit(0));
        self::assertSame('Pride (⊃｡•́‿•̀｡)⊃ and ⊂(´• ω •`⊂) Prejudice', $response->getHit(0)['_formatted']['title']);

        $response = $this->index->search('and', [
            'limit' => 1,
            'attributesToHighlight' => ['*'],
            'highlightPreTag' => '(⊃｡•́‿•̀｡)⊃ ',
            'highlightPostTag' => ' ⊂(´• ω •`⊂)',
        ], [
            'raw' => true,
        ]);

        self::assertArrayHasKey('_formatted', $response['hits'][0]);
        self::assertSame('Pride (⊃｡•́‿•̀｡)⊃ and ⊂(´• ω •`⊂) Prejudice', $response['hits'][0]['_formatted']['title']);
    }

    public function testParametersArray(): void
    {
        $response = $this->index->updateFilterableAttributes(['title']);
        $this->index->waitForTask($response['taskUid']);

        $response = $this->index->search('prince', [
            'limit' => 5,
            'offset' => 0,
            'attributesToRetrieve' => ['id', 'title'],
            'attributesToCrop' => ['id', 'title'],
            'cropLength' => 6,
            'attributesToHighlight' => ['title'],
            'filter' => 'title = "Le Petit Prince"',
            'showMatchesPosition' => true,
        ]);

        self::assertArrayHasKey('_matchesPosition', $response->getHit(0));
        self::assertArrayHasKey('title', $response->getHit(0)['_matchesPosition']);
        self::assertArrayHasKey('_formatted', $response->getHit(0));
        self::assertArrayNotHasKey('comment', $response->getHit(0));
        self::assertArrayNotHasKey('comment', $response->getHit(0)['_matchesPosition']);
        self::assertSame('Le Petit <em>Prince</em>', $response->getHit(0)['_formatted']['title']);

        $response = $this->index->search('prince', [
            'limit' => 5,
            'offset' => 0,
            'attributesToRetrieve' => ['id', 'title'],
            'attributesToCrop' => ['id', 'title'],
            'cropLength' => 6,
            'attributesToHighlight' => ['title'],
            'filter' => 'title = "Le Petit Prince"',
            'showMatchesPosition' => true,
        ], [
            'raw' => true,
        ]);

        self::assertArrayHasKey('_matchesPosition', $response['hits'][0]);
        self::assertArrayHasKey('title', $response['hits'][0]['_matchesPosition']);
        self::assertArrayHasKey('_formatted', $response['hits'][0]);
        self::assertArrayNotHasKey('comment', $response['hits'][0]);
        self::assertArrayNotHasKey('comment', $response['hits'][0]['_matchesPosition']);
        self::assertSame('Le Petit <em>Prince</em>', $response['hits'][0]['_formatted']['title']);
    }

    public function testParametersCanBeAStar(): void
    {
        $response = $this->index->updateFilterableAttributes(['title']);
        $this->index->waitForTask($response['taskUid']);

        $response = $this->index->search('prince', [
            'limit' => 5,
            'offset' => 0,
            'attributesToRetrieve' => ['*'],
            'attributesToCrop' => ['*'],
            'cropLength' => 6,
            'attributesToHighlight' => ['*'],
            'filter' => 'title = "Le Petit Prince"',
            'showMatchesPosition' => true,
        ]);

        self::assertArrayHasKey('_matchesPosition', $response->getHit(0));
        self::assertArrayHasKey('title', $response->getHit(0)['_matchesPosition']);
        self::assertArrayHasKey('_formatted', $response->getHit(0));
        self::assertArrayHasKey('comment', $response->getHit(0));
        self::assertArrayNotHasKey('comment', $response->getHit(0)['_matchesPosition']);
        self::assertSame('Le Petit <em>Prince</em>', $response->getHit(0)['_formatted']['title']);

        $response = $this->index->search('prince', [
            'limit' => 5,
            'offset' => 0,
            'attributesToRetrieve' => ['*'],
            'attributesToCrop' => ['*'],
            'cropLength' => 6,
            'attributesToHighlight' => ['*'],
            'filter' => 'title = "Le Petit Prince"',
            'showMatchesPosition' => true,
        ], [
            'raw' => true,
        ]);

        self::assertArrayHasKey('_matchesPosition', $response['hits'][0]);
        self::assertArrayHasKey('title', $response['hits'][0]['_matchesPosition']);
        self::assertArrayHasKey('_formatted', $response['hits'][0]);
        self::assertArrayHasKey('comment', $response['hits'][0]);
        self::assertArrayNotHasKey('comment', $response['hits'][0]['_matchesPosition']);
        self::assertSame('Le Petit <em>Prince</em>', $response['hits'][0]['_formatted']['title']);
    }

    public function testSearchWithFilterCanBeInt(): void
    {
        $response = $this->index->updateFilterableAttributes(['id', 'genre']);
        $this->index->waitForTask($response['taskUid']);

        $response = $this->index->search('prince', [
            'filter' => 'id < 12',
        ]);

        self::assertSame(1, $response->getEstimatedTotalHits());
        self::assertCount(1, $response->getHits());
        self::assertSame(4, $response->getHit(0)['id']);

        $response = $this->index->search('', [
            'filter' => 'genre = fantasy AND id < 12',
        ]);

        self::assertSame(2, $response->getEstimatedTotalHits());
        self::assertCount(2, $response->getHits());
        self::assertSame(1, $response->getHit(0)['id']);
        self::assertSame(4, $response->getHit(1)['id']);
    }

    public function testBasicSearchWithFacetDistribution(): void
    {
        $response = $this->index->updateFilterableAttributes(['genre']);
        $this->index->waitForTask($response['taskUid']);

        $response = $this->index->search('prince', [
            'facets' => ['genre'],
        ]);
        self::assertSame(2, $response->getHitsCount());
        self::assertArrayHasKey('facetDistribution', $response->toArray());
        self::assertArrayHasKey('genre', $response->getFacetDistribution());
        self::assertSame($response->getFacetDistribution()['genre']['fantasy'], 1);
        self::assertSame($response->getFacetDistribution()['genre']['adventure'], 1);

        $response = $this->index->search('prince', [
            'facets' => ['genre'],
        ], [
            'raw' => true,
        ]);
        self::assertSame(2, $response['estimatedTotalHits']);
        self::assertArrayHasKey('facetDistribution', $response);
        self::assertArrayHasKey('genre', $response['facetDistribution']);
        self::assertSame($response['facetDistribution']['genre']['fantasy'], 1);
        self::assertSame($response['facetDistribution']['genre']['adventure'], 1);
    }

    public function testBasicSearchWithFilters(): void
    {
        $response = $this->index->updateFilterableAttributes(['genre']);
        $this->index->waitForTask($response['taskUid']);

        $response = $this->index->search('prince', [
            'filter' => [['genre = fantasy']],
        ]);
        self::assertSame(1, $response->getHitsCount());
        self::assertArrayNotHasKey('facetDistribution', $response->getRaw());
        self::assertSame(4, $response->getHit(0)['id']);

        $response = $this->index->search('prince', [
            'filter' => [['genre = fantasy']],
        ], [
            'raw' => true,
        ]);
        self::assertSame(1, $response['estimatedTotalHits']);
        self::assertArrayNotHasKey('facetDistribution', $response);
        self::assertSame(4, $response['hits'][0]['id']);
    }

    public function testBasicSearchWithMultipleFilter(): void
    {
        $response = $this->index->updateFilterableAttributes(['genre']);
        $this->index->waitForTask($response['taskUid']);

        $response = $this->index->search('prince', [
            'filter' => ['genre = fantasy', ['genre = fantasy', 'genre = fantasy']],
        ]);
        self::assertSame(1, $response->getHitsCount());
        self::assertArrayNotHasKey('facetDistribution', $response->getRaw());
        self::assertSame(4, $response->getHit(0)['id']);

        $response = $this->index->search('prince', [
            'filter' => ['genre = fantasy', ['genre = fantasy', 'genre = fantasy']],
        ], [
            'raw' => true,
        ]);
        self::assertSame(1, $response['estimatedTotalHits']);
        self::assertArrayNotHasKey('facetDistribution', $response);
        self::assertSame(4, $response['hits'][0]['id']);
    }

    public function testCustomSearchWithFilterAndAttributesToRetrieve(): void
    {
        $response = $this->index->updateFilterableAttributes(['genre']);
        $this->index->waitForTask($response['taskUid']);

        $response = $this->index->search('prince', [
            'filter' => [['genre = fantasy']],
            'attributesToRetrieve' => ['id', 'title'],
        ]);
        self::assertSame(1, $response->getHitsCount());
        self::assertArrayNotHasKey('facetDistribution', $response->getRaw());
        self::assertSame(4, $response->getHit(0)['id']);
        self::assertArrayHasKey('id', $response->getHit(0));
        self::assertArrayHasKey('title', $response->getHit(0));
        self::assertArrayNotHasKey('comment', $response->getHit(0));

        $response = $this->index->search('prince', [
            'filter' => [['genre = fantasy']],
            'attributesToRetrieve' => ['id', 'title'],
        ], [
            'raw' => true,
        ]);
        self::assertSame(1, $response['estimatedTotalHits']);
        self::assertArrayNotHasKey('facetDistribution', $response);
        self::assertSame(4, $response['hits'][0]['id']);
        self::assertArrayHasKey('id', $response['hits'][0]);
        self::assertArrayHasKey('title', $response['hits'][0]);
        self::assertArrayNotHasKey('comment', $response['hits'][0]);
    }

    public function testSearchSortWithString(): void
    {
        $response = $this->index->updateRankingRules([
            'words',
            'typo',
            'sort',
            'proximity',
            'attribute',
            'exactness',
        ]);
        $this->index->waitForTask($response['taskUid']);
        $response = $this->index->updateSortableAttributes(['genre']);
        $this->index->waitForTask($response['taskUid']);

        $response = $this->index->search('prince', [
            'sort' => ['genre:asc'],
        ]);
        self::assertSame(2, $response->getHitsCount());
        self::assertArrayNotHasKey('facetDistribution', $response->getRaw());
        self::assertSame(456, $response->getHit(0)['id']);

        $response = $this->index->search('prince', [
            'sort' => ['genre:asc'],
        ], [
            'raw' => true,
        ]);
        self::assertSame(2, $response['estimatedTotalHits']);
        self::assertArrayNotHasKey('facetDistribution', $response);
        self::assertSame(456, $response['hits'][0]['id']);
    }

    public function testSearchSortWithInt(): void
    {
        $response = $this->index->updateRankingRules([
            'words',
            'typo',
            'sort',
            'proximity',
            'attribute',
            'exactness',
        ]);
        $this->index->waitForTask($response['taskUid']);
        $response = $this->index->updateSortableAttributes(['id']);
        $this->index->waitForTask($response['taskUid']);

        $response = $this->index->search('prince', [
            'sort' => ['id:asc'],
        ]);
        self::assertSame(2, $response->getHitsCount());
        self::assertArrayNotHasKey('facetDistribution', $response->getRaw());
        self::assertSame(4, $response->getHit(0)['id']);

        $response = $this->index->search('prince', [
            'sort' => ['id:asc'],
        ], [
            'raw' => true,
        ]);
        self::assertSame(2, $response['estimatedTotalHits']);
        self::assertArrayNotHasKey('facetDistribution', $response);
        self::assertSame(4, $response['hits'][0]['id']);
    }

    public function testSearchSortWithMultipleParameter(): void
    {
        $response = $this->index->updateRankingRules([
            'words',
            'typo',
            'sort',
            'proximity',
            'attribute',
            'exactness',
        ]);
        $this->index->waitForTask($response['taskUid']);
        $response = $this->index->updateSortableAttributes(['id', 'title']);
        $this->index->waitForTask($response['taskUid']);

        $response = $this->index->search('prince', [
            'sort' => ['id:asc', 'title:asc'],
        ]);
        self::assertSame(2, $response->getHitsCount());
        self::assertArrayNotHasKey('facetDistribution', $response->getRaw());
        self::assertSame(4, $response->getHit(0)['id']);

        $response = $this->index->search('prince', [
            'sort' => ['id:asc', 'title:asc'],
        ], [
            'raw' => true,
        ]);
        self::assertSame(2, $response['estimatedTotalHits']);
        self::assertArrayNotHasKey('facetDistribution', $response);
        self::assertSame(4, $response['hits'][0]['id']);
    }

    public function testSearchWithPhraseSearch(): void
    {
        $response = $this->index->rawSearch('coco "harry"');

        self::assertCount(1, $response['hits']);
        self::assertEquals(4, $response['hits'][0]['id']);
        self::assertEquals('Harry Potter and the Half-Blood Prince', $response['hits'][0]['title']);
    }

    public function testBasicSearchWithRawSearch(): void
    {
        $response = $this->index->rawSearch('prince');

        $this->assertEstimatedPagination($response);
        self::assertSame(2, $response['estimatedTotalHits']);
        self::assertCount(2, $response['hits']);
        self::assertEquals('Le Petit Prince', $response['hits'][0]['title']);
    }

    public function testBasicSearchWithRawOption(): void
    {
        $response = $this->index->search('prince', [], ['raw' => true]);

        $this->assertEstimatedPagination($response);
        self::assertSame(2, $response['estimatedTotalHits']);
        self::assertCount(2, $response['hits']);
    }

    public function testBasicSearchWithTransformHitsOptionToFilter(): void
    {
        $keepLePetitPrinceFunc = function (array $hits): array {
            return array_filter(
                $hits,
                function (array $hit): bool { return 'Le Petit Prince' === $hit['title']; }
            );
        };

        $response = $this->index->search('prince', [], $options = ['transformHits' => $keepLePetitPrinceFunc]);

        $this->assertEstimatedPagination($response->toArray());
        self::assertSame('Le Petit Prince', $response->getHit(0)['title']);
        self::assertSame(2, $response->getEstimatedTotalHits());
        self::assertSame(1, $response->getHitsCount());
        self::assertCount(1, $response);
    }

    public function testBasicSearchWithTransformHitsOptionToMap(): void
    {
        $titlesToUpperCaseFunc = function (array $hits): array {
            return array_map(
                function (array $hit) {
                    $hit['title'] = strtoupper($hit['title']);

                    return $hit;
                },
                $hits
            );
        };

        $response = $this->index->search('prince', [], ['transformHits' => $titlesToUpperCaseFunc]);

        $this->assertEstimatedPagination($response->toArray());
        self::assertSame(2, $response->getEstimatedTotalHits());
        self::assertSame(2, $response->getHitsCount());
        self::assertCount(2, $response->getHits());
        self::assertSame('LE PETIT PRINCE', $response->getHits()[0]['title']);
    }

    public function testBasicSearchCannotBeFilteredOnRawResult(): void
    {
        $keepLePetitPrinceFunc = function (array $hits): array {
            return array_filter(
                $hits,
                function (array $hit): bool { return 'Le Petit Prince' === $hit['title']; }
            );
        };

        $response = $this->index->search('prince', [], [
            'raw' => true,
            'transformHits' => $keepLePetitPrinceFunc,
        ]);

        $this->assertEstimatedPagination($response);
        self::assertSame(2, $response['estimatedTotalHits']);
        self::assertCount(2, $response['hits']);
    }

    public function testBasicSearchCanBeFilteredOnRawResultIfUsingToArray(): void
    {
        $keepLePetitPrinceFunc = function (array $hits): array {
            return array_filter(
                $hits,
                function (array $hit): bool { return 'Le Petit Prince' === $hit['title']; }
            );
        };

        $response = $this->index->search('prince', [], ['transformHits' => $keepLePetitPrinceFunc])->toArray();

        $this->assertEstimatedPagination($response);
        self::assertSame(2, $response['estimatedTotalHits']);
        self::assertCount(1, $response['hits']);
        self::assertEquals('Le Petit Prince', $response['hits'][0]['title']);
    }

    public function testBasicSearchWithFacetsOption(): void
    {
        $response = $this->index->updateFilterableAttributes(['genre']);
        $this->index->waitForTask($response['taskUid']);

        $response = $this->index->search(
            'prince',
            ['facets' => ['genre']]
        );

        self::assertCount(2, $response->getFacetDistribution()['genre']);
        self::assertEquals(1, $response->getFacetDistribution()['genre']['adventure']);
        self::assertEquals(1, $response->getFacetDistribution()['genre']['fantasy']);
        self::assertCount(2, $response->getRaw()['facetDistribution']['genre']);
        self::assertEquals($response->getRaw()['hits'], $response->getHits());
        self::assertEquals($response->getRaw()['facetDistribution'], $response->getFacetDistribution());
    }

    public function testBasicSearchWithFacetsOptionAndMultipleFacets(): void
    {
        $response = $this->index->addDocuments([['id' => 32, 'title' => 'The Witcher', 'genre' => 'adventure', 'adaptation' => 'video game']]);
        $this->index->waitForTask($response['taskUid']);
        $response = $this->index->updateFilterableAttributes(['genre', 'adaptation']);
        $this->index->waitForTask($response['taskUid']);

        $response = $this->index->search(
            'witch',
            ['facets' => ['genre', 'adaptation']]
        );

        self::assertCount(1, $response->getFacetDistribution()['genre']);
        self::assertEquals(1, $response->getFacetDistribution()['genre']['adventure']);
        self::assertCount(1, $response->getFacetDistribution()['adaptation']);
        self::assertEquals(1, $response->getFacetDistribution()['adaptation']['video game']);
        self::assertCount(1, $response->getRaw()['facetDistribution']['adaptation']);
        self::assertCount(1, $response->getRaw()['facetDistribution']['genre']);
        self::assertEquals($response->getRaw()['hits'], $response->getHits());
        self::assertEquals($response->getRaw()['facetDistribution'], $response->getFacetDistribution());
    }

    public function testVectorSearch(): void
    {
        $http = new Client($this->host, getenv('MEILISEARCH_API_KEY'));
        $http->patch('/experimental-features', ['vectorStore' => true]);
        $index = $this->createEmptyIndex($this->safeIndexName());

        $promise = $index->updateEmbedders(['default' => ['source' => 'userProvided', 'dimensions' => 1]]);
        $this->assertIsValidPromise($promise);
        $index->waitForTask($promise['taskUid']);

        $response = $index->search('', ['vector' => [1], 'hybrid' => ['semanticRatio' => 1.0]]);

        self::assertEmpty($response->getHits());
    }

    public function testShowRankingScoreDetails(): void
    {
        $http = new Client($this->host, getenv('MEILISEARCH_API_KEY'));
        $http->patch('/experimental-features', ['scoreDetails' => true]);

        $response = $this->index->search('the', ['showRankingScoreDetails' => true]);
        $hit = $response->getHits()[0];

        self::assertArrayHasKey('_rankingScoreDetails', $hit);
    }

    public function testBasicSearchWithTransformFacetsDritributionOptionToFilter(): void
    {
        $response = $this->index->updateFilterableAttributes(['genre']);
        $this->index->waitForTask($response['taskUid']);

        $filterAllFacets = function (array $facets): array {
            $filterOneFacet = function (array $facet): array {
                return array_filter(
                    $facet,
                    function (int $facetValue): bool { return 1 < $facetValue; },
                    ARRAY_FILTER_USE_BOTH
                );
            };

            return array_map($filterOneFacet, $facets);
        };

        $response = $this->index->search(
            null,
            ['facets' => ['genre']],
            ['transformFacetDistribution' => $filterAllFacets]
        );

        $this->assertEstimatedPagination($response->toArray());
        self::assertEquals($response->getRaw()['hits'], $response->getHits());
        self::assertNotEquals($response->getRaw()['facetDistribution'], $response->getFacetDistribution());
        self::assertCount(3, $response->getRaw()['facetDistribution']['genre']);
        self::assertCount(2, $response->getFacetDistribution()['genre']);
        self::assertEquals(3, $response->getFacetDistribution()['genre']['romance']);
        self::assertEquals(2, $response->getFacetDistribution()['genre']['fantasy']);
    }

    public function testSearchWithAttributesToSearchOn(): void
    {
        $response = $this->index->updateSearchableAttributes(['comment', 'title']);
        $this->index->waitForTask($response['taskUid']);

        $response = $this->index->search('the', ['attributesToSearchOn' => ['comment']]);

        self::assertEquals('The best book', $response->getHits()[0]['comment']);
    }

    public function testSearchWithShowRankingScore(): void
    {
        $response = $this->index->search('the', ['showRankingScore' => true]);

        self::assertArrayHasKey('_rankingScore', $response->getHits()[0]);
    }

    public function testBasicSearchWithTransformFacetsDritributionOptionToMap(): void
    {
        $response = $this->index->updateFilterableAttributes(['genre']);
        $this->index->waitForTask($response['taskUid']);

        $facetsToUpperFunc = function (array $facets): array {
            $changeOneFacet = function (array $facet): array {
                $result = [];
                foreach ($facet as $k => $v) {
                    $result[strtoupper($k)] = $v;
                }

                return $result;
            };

            return array_map($changeOneFacet, $facets);
        };

        $response = $this->index->search(
            null,
            ['facets' => ['genre']],
            ['transformFacetDistribution' => $facetsToUpperFunc]
        );

        $this->assertEstimatedPagination($response->toArray());
        self::assertEquals($response->getRaw()['hits'], $response->getHits());
        self::assertNotEquals($response->getRaw()['facetDistribution'], $response->getFacetDistribution());
        self::assertCount(3, $response->getFacetDistribution()['genre']);
        self::assertEquals(3, $response->getFacetDistribution()['genre']['ROMANCE']);
        self::assertEquals(2, $response->getFacetDistribution()['genre']['FANTASY']);
        self::assertEquals(1, $response->getFacetDistribution()['genre']['ADVENTURE']);
    }

    public function testBasicSearchWithTransformFacetsDritributionOptionToOder(): void
    {
        $response = $this->index->updateFilterableAttributes(['genre']);
        $this->index->waitForTask($response['taskUid']);

        $facetsToUpperFunc = function (array $facets): array {
            $sortOneFacet = function (array $facet): array {
                ksort($facet);

                return $facet;
            };

            return array_map($sortOneFacet, $facets);
        };

        $response = $this->index->search(
            null,
            ['facets' => ['genre']],
            ['transformFacetDistribution' => $facetsToUpperFunc]
        );

        $this->assertEstimatedPagination($response->toArray());
        self::assertEquals($response->getRaw()['hits'], $response->getHits());
        self::assertEquals('adventure', array_key_first($response->getFacetDistribution()['genre']));
        self::assertEquals('romance', array_key_last($response->getFacetDistribution()['genre']));
        self::assertCount(3, $response->getFacetDistribution()['genre']);
        self::assertEquals(3, $response->getFacetDistribution()['genre']['romance']);
        self::assertEquals(2, $response->getFacetDistribution()['genre']['fantasy']);
        self::assertEquals(1, $response->getFacetDistribution()['genre']['adventure']);
    }

    public function testSearchAndRetrieveFacetStats(): void
    {
        $this->index = $this->createEmptyIndex($this->safeIndexName());
        $this->index->updateFilterableAttributes(['info.reviewNb']);

        $promise = $this->index->updateDocuments(self::NESTED_DOCUMENTS);
        $this->index->waitForTask($promise['taskUid']);

        $response = $this->index->search(
            null,
            ['facets' => ['info.reviewNb']],
        );

        self::assertEquals(['info.reviewNb' => ['min' => 50, 'max' => 1000]], $response->getFacetStats());
    }
}
