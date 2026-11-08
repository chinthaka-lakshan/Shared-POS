<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Filament\Resources\ProductResource\RelationManagers;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return in_array(auth()->user()->role, ['super_admin', 'shop_owner']);
    }

    public static function canDeleteAny(): bool
    {
        return in_array(auth()->user()->role, ['super_admin', 'shop_owner']);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([

                Forms\Components\TextInput::make('barcode'),
                Forms\Components\TextInput::make('sku')
                    ->label('SKU'),
                Forms\Components\TextInput::make('name')
                    ->required(),
                Forms\Components\Textarea::make('description')
                    ->columnSpanFull(),
                Forms\Components\Select::make('unit')
                    ->options([
                        'Kg' => 'Kg',
                        'Litre' => 'Litre',
                        'Pieces' => 'Pieces',
                        'Box' => 'Box',
                        'Carton' => 'Carton',
                    ])
                    ->default('Pieces')
                    ->required(),
                Forms\Components\TextInput::make('cost_price')
                    ->required()
                    ->numeric()
                    ->default(null),
                Forms\Components\TextInput::make('selling_price')
                    ->required()
                    ->numeric()
                    ->gte('cost_price')
                    ->default(null),
                Forms\Components\TextInput::make('stock_quantity')
                    ->label('Initial Product Count / Stock')
                    ->required()
                    ->numeric()
                    ->default(null),
                Forms\Components\TextInput::make('min_reorder_level')
                    ->label('Low Stock Level (Min Reorder)')
                    ->required()
                    ->numeric()
                    ->default(null),
                Forms\Components\Toggle::make('is_active')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([

                Tables\Columns\TextColumn::make('barcode')
                    ->searchable(),
                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('total_stock')
                    ->label('Total Stock')
                    ->state(function (Product $record): float {
                        return (float) $record->total_stock;
                    })
                    ->badge()
                    ->color(fn (Product $record): string => $record->total_stock < $record->min_reorder_level ? 'danger' : 'success'),
                Tables\Columns\TextColumn::make('min_reorder_level')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\Action::make('print_barcode')
                    ->label('Print Barcode')
                    ->icon('heroicon-o-qr-code')
                    ->modalHeading(fn ($record) => 'Barcode: ' . $record->name)
                    ->modalContent(fn ($record) => view('filament.components.print-barcode', ['record' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
                Tables\Actions\Action::make('add_stock')
                    ->label('Add Stock')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->form([
                        Forms\Components\TextInput::make('quantity')
                            ->label('Stock Quantity to Add')
                            ->numeric()
                            ->required()
                            ->default(null),
                    ])
                    ->action(function (Product $record, array $data): void {
                        $record->increment('stock_quantity', $data['quantity']);
                        // Optional: trigger success notification
                        \Filament\Notifications\Notification::make()
                            ->title('Stock added successfully')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\BatchesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
