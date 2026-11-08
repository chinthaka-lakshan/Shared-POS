<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SaleReturnResource\Pages;
use App\Filament\Resources\SaleReturnResource\RelationManagers;
use App\Models\SaleReturn;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SaleReturnResource extends Resource
{
    protected static ?string $model = SaleReturn::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-uturn-left';
    protected static ?string $navigationGroup = 'Financial Reports';

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
                Forms\Components\Hidden::make('tenant_id')
                    ->default(fn () => filament()->getTenant()->id),
                Forms\Components\Select::make('sale_id')
                    ->relationship('sale', 'invoice_number')
                    ->required()
                    ->searchable(),
                Forms\Components\TextInput::make('total_refund')
                    ->required()
                    ->numeric(),
                Forms\Components\Select::make('condition')
                    ->options([
                        'good' => 'Good Condition (Return to Stock)',
                        'damaged' => 'Damaged (Write-off)',
                    ])
                    ->required()
                    ->default('good'),
                Forms\Components\DateTimePicker::make('returned_at')
                    ->required()
                    ->default(now()),
                Forms\Components\Textarea::make('reason')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sale.invoice_number')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('returned_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('total_refund')->numeric()->sortable(),
                Tables\Columns\TextColumn::make('condition')->badge()->color(fn (string $state): string => match ($state) {
                    'good' => 'success',
                    'damaged' => 'danger',
                    default => 'gray',
                }),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSaleReturns::route('/'),
            'create' => Pages\CreateSaleReturn::route('/create'),
            'view' => Pages\ViewSaleReturn::route('/{record}'),
            'edit' => Pages\EditSaleReturn::route('/{record}/edit'),
        ];
    }
}
